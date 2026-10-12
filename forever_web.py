"""Forever CMS: runs the PHP website together with the repack's support site.

The launcher has one "Website" line (localservers.json webExe / webArgs). Setup-CMS points it at this file:

    web\\python\\python.exe  cms\\forever_web.py  web\\support_server.py  web\\support_site.json

This starts the CMS (cms\\php\\php.exe, http://127.0.0.1:8080) and then runs the support site in this process,
exactly as before, so the launcher still sees and stops its Website on port 443. The CMS is tied to this
process and stops with it, however it ends. Without the CMS's PHP the support site simply runs alone.

FOREVER_CMS_BIND and FOREVER_CMS_PORT change the address (default 127.0.0.1 and 8080).
"""
import ctypes
import os
import runpy
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
BIND = os.environ.get('FOREVER_CMS_BIND', '127.0.0.1')
PORT = os.environ.get('FOREVER_CMS_PORT', '8080')
KEEP = []                                           # handles that must live as long as this process


def tie_to_this_process(child):
    """Puts the child in a job object that Windows closes with this process: no PHP is left behind after a kill."""
    if os.name != 'nt':
        return
    k32 = ctypes.WinDLL('kernel32', use_last_error=True)

    class BasicLimits(ctypes.Structure):
        _fields_ = [('PerProcessUserTimeLimit', ctypes.c_int64), ('PerJobUserTimeLimit', ctypes.c_int64), ('LimitFlags', ctypes.c_uint32),
                    ('MinimumWorkingSetSize', ctypes.c_size_t), ('MaximumWorkingSetSize', ctypes.c_size_t), ('ActiveProcessLimit', ctypes.c_uint32),
                    ('Affinity', ctypes.c_size_t), ('PriorityClass', ctypes.c_uint32), ('SchedulingClass', ctypes.c_uint32)]

    class IoCounters(ctypes.Structure):
        _fields_ = [(name, ctypes.c_uint64) for name in ('ReadOps', 'WriteOps', 'OtherOps', 'ReadBytes', 'WriteBytes', 'OtherBytes')]

    class ExtendedLimits(ctypes.Structure):
        _fields_ = [('BasicLimitInformation', BasicLimits), ('IoInfo', IoCounters), ('ProcessMemoryLimit', ctypes.c_size_t),
                    ('JobMemoryLimit', ctypes.c_size_t), ('PeakProcessMemoryUsed', ctypes.c_size_t), ('PeakJobMemoryUsed', ctypes.c_size_t)]

    k32.CreateJobObjectW.restype = ctypes.c_void_p
    k32.SetInformationJobObject.argtypes = [ctypes.c_void_p, ctypes.c_int, ctypes.c_void_p, ctypes.c_uint32]
    k32.AssignProcessToJobObject.argtypes = [ctypes.c_void_p, ctypes.c_void_p]
    job = k32.CreateJobObjectW(None, None)
    if not job:
        return
    limits = ExtendedLimits()
    limits.BasicLimitInformation.LimitFlags = 0x2000            # JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE
    if k32.SetInformationJobObject(job, 9, ctypes.byref(limits), ctypes.sizeof(limits)):   # 9 = extended limit information
        k32.AssignProcessToJobObject(job, int(child._handle))
    KEEP.append(job)


def start_cms():
    php = os.path.join(HERE, 'php', 'php.exe')
    if not os.path.isfile(php):
        print('Forever CMS: cms\\php\\php.exe not found, the CMS is not started')
        return None
    for folder in ('logs', os.path.join('tmp', 'sessions')):
        os.makedirs(os.path.join(HERE, folder), exist_ok=True)
    log = open(os.path.join(HERE, 'logs', 'server.log'), 'ab')
    KEEP.append(log)
    env = dict(os.environ, FOREVER_CMS=HERE.replace('\\', '/'))
    try:
        child = subprocess.Popen([php, '-c', os.path.join(HERE, 'php', 'php.ini'), '-S', '%s:%s' % (BIND, PORT),
                                  '-t', os.path.join(HERE, 'www'), os.path.join(HERE, 'router.php')],
                                 cwd=HERE, env=env, stdin=subprocess.DEVNULL, stdout=log, stderr=log)
    except OSError as error:
        print('Forever CMS: could not start PHP:', error)
        return None
    tie_to_this_process(child)
    print('Forever CMS: http://%s:%s' % (BIND, PORT))
    return child


def main():
    cms = start_cms()
    try:
        if len(sys.argv) > 1:
            # the support site, as if it had been started directly
            sys.argv = sys.argv[1:]
            runpy.run_path(sys.argv[0], run_name='__main__')
        elif cms:
            cms.wait()
    except KeyboardInterrupt:
        pass
    finally:
        if cms and cms.poll() is None:
            cms.terminate()


if __name__ == '__main__':
    main()
