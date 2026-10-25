"""Minimal read-only WDC5 (DB2) reader for ForeverCMS build tools.

Only reads plain, unencrypted sections. Every column is returned as a list of
raw 32-bit unsigned values; callers reinterpret floats with as_float().
"""
import struct


def as_float(value):
    return struct.unpack('<f', struct.pack('<I', value & 0xFFFFFFFF))[0]


def as_int(value):
    return struct.unpack('<i', struct.pack('<I', value & 0xFFFFFFFF))[0]


class DB2:
    def __init__(self, path):
        with open(path, 'rb') as handle:
            self.data = data = handle.read()
        if data[:4] != b'WDC5':
            raise ValueError(f'{path}: not a WDC5 file')
        offset = 4 + 4 + 128
        (self.record_count, self.field_count, self.record_size, self.string_table_size,
         self.table_hash, self.layout_hash, self.min_id, self.max_id, self.locale) = struct.unpack_from('<9I', data, offset)
        offset += 36
        self.flags, self.id_index = struct.unpack_from('<HH', data, offset)
        offset += 4
        (self.total_field_count, self.bitpacked_offset, self.lookup_columns, storage_size,
         common_size, pallet_size, section_count) = struct.unpack_from('<7I', data, offset)
        offset += 28
        sections = []
        for _ in range(section_count):
            sections.append(struct.unpack_from('<QIIIIIIII', data, offset))
            offset += 40
        offset += 4 * self.field_count  # field_structure (size, position)
        self.storage = []
        for _ in range(storage_size // 24):
            self.storage.append(struct.unpack_from('<HHIIIII', data, offset))
            offset += 24
        pallet_start = offset
        common_start = pallet_start + pallet_size
        # Per-field pallet / common blocks, in field order.
        self.pallet, self.common = {}, {}
        pallet_cursor, common_cursor = pallet_start, common_start
        for index, (_bits_offset, _bits, additional, kind, *_vals) in enumerate(self.storage):
            if kind in (3, 4):
                self.pallet[index] = pallet_cursor
                pallet_cursor += additional
            elif kind == 2:
                entries = {}
                for entry in range(additional // 8):
                    key, value = struct.unpack_from('<II', data, common_cursor + entry * 8)
                    entries[key] = value
                self.common[index] = entries
                common_cursor += additional

        if self.flags & 1:
            raise ValueError(f'{path}: sparse (offset-map) tables are not supported')

        self.rows = []
        string_base = 0
        record_base_total = 0
        for (tact_key, file_offset, rec_count, str_size, _end, id_size, rel_size, _map_count, copy_count) in sections:
            if tact_key != 0:
                record_base_total += rec_count
                string_base += str_size
                continue
            records_offset = file_offset
            strings_offset = records_offset + rec_count * self.record_size
            cursor = strings_offset + str_size
            ids = list(struct.unpack_from(f'<{id_size // 4}I', data, cursor)) if id_size else []
            cursor += id_size
            copies = [struct.unpack_from('<II', data, cursor + i * 8) for i in range(copy_count)]
            cursor += copy_count * 8
            relations = {}
            if rel_size:
                count, _min, _max = struct.unpack_from('<3I', data, cursor)
                for i in range(count):
                    foreign, index = struct.unpack_from('<II', data, cursor + 12 + i * 8)
                    relations[index] = foreign
            for index in range(rec_count):
                record_offset = records_offset + index * self.record_size
                fields = [self._field(record_offset, f, strings_offset, str_size) for f in range(len(self.storage))]
                record_id = ids[index] if ids else fields[self.id_index][0]
                self.rows.append({'id': record_id, 'fields': fields, 'relation': relations.get(index)})
            by_id = {row['id']: row for row in self.rows}
            for new_id, old_id in copies:
                if old_id in by_id:
                    self.rows.append({**by_id[old_id], 'id': new_id})
            string_base += str_size
            record_base_total += rec_count

    def _bits(self, record_offset, bit_offset, bit_count):
        start = record_offset + bit_offset // 8
        chunk = int.from_bytes(self.data[start:start + 9], 'little')
        return (chunk >> (bit_offset % 8)) & ((1 << bit_count) - 1)

    def _field(self, record_offset, index, strings_offset, strings_size):
        bit_offset, bit_count, _additional, kind, val1, val2, val3 = self.storage[index]
        if kind == 0:  # inline, byte aligned
            byte_offset = bit_offset // 8
            count = max(1, bit_count // 32) if bit_count >= 32 else 1
            if bit_count >= 32:
                values = list(struct.unpack_from(f'<{count}I', self.data, record_offset + byte_offset))
                # keep the absolute file address so string columns can be resolved
                return values + [('addr', record_offset + byte_offset)]
            return [self._bits(record_offset, bit_offset, bit_count)]
        if kind == 1:
            return [self._bits(record_offset, bit_offset, bit_count)]
        if kind == 5:
            value = self._bits(record_offset, bit_offset, bit_count)
            if value & (1 << (bit_count - 1)):
                value -= 1 << bit_count
            return [value & 0xFFFFFFFF]
        if kind == 2:
            return [('common', index, val1)]
        if kind == 3:
            slot = self._bits(record_offset, bit_offset, bit_count)
            return [struct.unpack_from('<I', self.data, self.pallet[index] + slot * 4)[0]]
        if kind == 4:
            slot = self._bits(record_offset, bit_offset, bit_count)
            base = self.pallet[index] + slot * 4 * val3
            return list(struct.unpack_from(f'<{val3}I', self.data, base))
        raise ValueError(f'unknown storage type {kind}')

    def records(self):
        """Yields (id, columns, relation) with common-data columns resolved."""
        for row in self.rows:
            columns = []
            for index, values in enumerate(row['fields']):
                if values and isinstance(values[0], tuple) and values[0][0] == 'common':
                    columns.append([self.common.get(index, {}).get(row['id'], values[0][2])])
                else:
                    columns.append([v for v in values if not isinstance(v, tuple)])
            yield row['id'], columns, row['relation']

    def string(self, row_id, column):
        """Resolves an inline string column (WDC3+: offset relative to the field)."""
        for row in self.rows:
            if row['id'] != row_id:
                continue
            values = row['fields'][column]
            address = [v for v in values if isinstance(v, tuple) and v[0] == 'addr']
            if not address:
                return ''
            target = address[0][1] + values[0]
            end = self.data.index(b'\0', target)
            return self.data[target:end].decode('utf-8', 'replace')
        return ''
