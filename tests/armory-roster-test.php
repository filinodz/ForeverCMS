<?php
define('ALLOWED_ACCESS',true);
require __DIR__.'/../www/includes/forever.php';
require __DIR__.'/../www/includes/forever_models.php';
$checks=0;
function check_roster($value,$message){global $checks;if(!$value)throw new RuntimeException($message);echo 'PASS ',$message,"\n";$checks++;}
foreach([0,1] as $sex){foreach([95,96] as $race){$identity=forever_character_model_identity($race,$sex);check_roster($identity['race']===10&&$identity['gender']===$sex&&$identity['skyborne']&&$identity['model']==='bloodelf'.($sex?'female':'male'),'Skyborne uses verified client fallback '.$race.'/'.$sex);}}
check_roster(forever_character_model_identity(1,0)['model']==='humanmale','Human identity is preserved');
check_roster(forever_character_model_identity(4,1)['model']==='nightelffemale','Night Elf identity is preserved');
check_roster(forever_character_model_identity(999,0)['model']===null,'Unknown race never impersonates a Human');
$base_path='/';$public_realms=[['id'=>70,'name'=>'Test realm','type'=>'Normal']];$selected_realm=$public_realms[0];$database_available=true;
$class_colors=[1=>'#c79c6e',8=>'#69ccf0'];$class_icons=[1=>'warrior.webp',8=>'mage.webp'];$map_names=[0=>'Eastern Kingdoms'];
$players=[['guid'=>900001,'name'=>'Test <script>alert(1)</script>','class'=>1,'race'=>95,'gender'=>0,'level'=>60,'map'=>0,'is_bot'=>0],['guid'=>900002,'name'=>'Test Bot','class'=>8,'race'=>2,'gender'=>1,'level'=>40,'map'=>0,'is_bot'=>1]];
$bot_count=1;$alliance_count=1;$horde_count=0;
foreach(['fr','en'] as $langCode){ob_start();include __DIR__.'/../www/includes/online_roster_view.php';$html=ob_get_clean();
 check_roster(!str_contains($html,'<script>alert')&&str_contains($html,'&lt;script&gt;'),'Roster escapes names in '.$langCode);
 check_roster(str_contains($html,'data-presence-type="bot"')&&str_contains($html,'data-presence-type="player"'),'Bots and players remain distinguishable in '.$langCode);
 check_roster(str_contains($html,'guid=900001&amp;realm=70'),'Armory links retain the selected realm in '.$langCode);
 check_roster(str_contains($html,'skyborne/high-order.svg'),'High Order uses its real race icon in '.$langCode);
 check_roster(str_contains($html,'id="rosterFaction"')&&str_contains($html,'id="rosterClass"'),'Faction and class filters are available in '.$langCode);
}
echo "$checks checks passed. No database or live player state was changed.\n";
