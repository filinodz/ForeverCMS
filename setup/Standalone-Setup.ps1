param([string]$PhpPath='', [string]$PreviewImage='')
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
[Windows.Forms.Application]::EnableVisualStyles()
$cmsRoot=Split-Path -Parent $PSScriptRoot
if(-not $PhpPath){$bundled=Join-Path $cmsRoot 'php\php.exe';if(Test-Path -LiteralPath $bundled){$PhpPath=$bundled}else{$phpCommand=Get-Command php -ErrorAction SilentlyContinue;if($phpCommand){$PhpPath=$phpCommand.Source}}}
if(-not $PhpPath){[Windows.Forms.MessageBox]::Show('Install PHP 8.2+ with mysqli, gmp, mbstring, openssl and dom, or place its portable runtime in the php folder. Then run this assistant again.','ForeverCMS');exit 1}
$form=New-Object Windows.Forms.Form
$form.Text='ForeverCMS - Standalone setup';$form.ClientSize=New-Object Drawing.Size(760,730);$form.StartPosition='CenterScreen';$form.BackColor=[Drawing.Color]::FromArgb(19,23,28);$form.ForeColor=[Drawing.Color]::Ivory;$form.Font=New-Object Drawing.Font('Segoe UI',10);$form.FormBorderStyle='FixedDialog';$form.MaximizeBox=$false
$title=New-Object Windows.Forms.Label;$title.Text='Your website. Your server.';$title.Font=New-Object Drawing.Font('Georgia',23);$title.SetBounds(28,22,700,42);$form.Controls.Add($title)
$intro=New-Object Windows.Forms.Label;$intro.Text='Connect ForeverCMS to your existing ForeverCore databases. No launcher is required.';$intro.SetBounds(30,74,700,42);$form.Controls.Add($intro)
$fields=@{};$rows=@(@('site_name','Website name','ForeverCMS'),@('site_url','Website URL','http://127.0.0.1:8080'),@('host','MySQL host','127.0.0.1'),@('port','MySQL port','3306'),@('user','Database user',''),@('password','Database password',''),@('auth','Accounts database','auth'),@('world','World database','world'),@('characters','Characters database','characters'),@('site','Website database (separate)','forever_site'),@('realm_id','Realm ID','70'),@('realm_name','Realm name','Normal'),@('realm_port','Realm game port','8085'),@('portal','Game hostname / IP','127.0.0.1'))
$row=0
foreach($entry in $rows){$label=New-Object Windows.Forms.Label;$label.Text=$entry[1];$label.SetBounds(30,123+$row*30,255,25);$form.Controls.Add($label);$field=New-Object Windows.Forms.TextBox;$field.Text=$entry[2];$field.SetBounds(300,120+$row*30,425,26);$field.BackColor=[Drawing.Color]::FromArgb(31,37,44);$field.ForeColor=[Drawing.Color]::Ivory;if($entry[0]-eq 'password'){$field.UseSystemPasswordChar=$true};$fields[$entry[0]]=$field;$form.Controls.Add($field);$row++}
$status=New-Object Windows.Forms.Label;$status.SetBounds(30,555,695,62);$status.Text='Only the website schema is created. Existing accounts, characters and server files stay intact.';$form.Controls.Add($status)
$progress=New-Object Windows.Forms.ProgressBar;$progress.SetBounds(30,624,695,5);$form.Controls.Add($progress)
$test=New-Object Windows.Forms.Button;$test.Text='Test connection';$test.SetBounds(30,650,170,40);$form.Controls.Add($test)
$install=New-Object Windows.Forms.Button;$install.Text='Install / configure';$install.SetBounds(215,650,210,40);$install.BackColor=[Drawing.Color]::FromArgb(202,175,117);$install.ForeColor=[Drawing.Color]::Black;$form.Controls.Add($install)
$credit=New-Object Windows.Forms.Label;$credit.Text='Made with love by FilinoDZ';$credit.SetBounds(470,662,255,25);$form.Controls.Add($credit)
$state=@{Busy=$false}
$form.Add_FormClosing({param($sender,$e)if($state.Busy){$e.Cancel=$true;$status.Text='Please wait for the current database step to finish.'}})
$execute={param($check)
    if($state.Busy){return}
    if(-not $check -and (Test-Path -LiteralPath (Join-Path $cmsRoot 'www\includes\config.database.php'))){if([Windows.Forms.MessageBox]::Show('This installation is already configured. Save a backup and replace its database/realm connection settings? Game data will not be reset.','Update configuration','YesNo','Question')-ne 'Yes'){return}}
    $state.Busy=$true;$test.Enabled=$false;$install.Enabled=$false;$progress.Style='Marquee';$status.Text='Checking prerequisites and connecting to the selected database...'
    try{
        $values=@{};foreach($key in $fields.Keys){$values[$key]=$fields[$key].Text}
        $psi=New-Object Diagnostics.ProcessStartInfo;$psi.FileName=$PhpPath;$psi.WorkingDirectory=$cmsRoot;$psi.UseShellExecute=$false;$psi.CreateNoWindow=$true;$psi.RedirectStandardInput=$true;$psi.RedirectStandardOutput=$true;$psi.RedirectStandardError=$true;$psi.EnvironmentVariables['FOREVER_CMS']=$cmsRoot
        $extensionPath=Join-Path (Split-Path -Parent $PhpPath) 'ext';$psi.Arguments='-d "display_errors=0" -d "extension_dir='+$extensionPath+'" "'+(Join-Path $PSScriptRoot 'standalone.php')+'"'+$(if($check){' --check'}else{''})
        $proc=[Diagnostics.Process]::Start($psi);$proc.StandardInput.Write(($values|ConvertTo-Json -Compress));$proc.StandardInput.Close();$output=$proc.StandardOutput.ReadToEndAsync();$errors=$proc.StandardError.ReadToEndAsync();$watch=[Diagnostics.Stopwatch]::StartNew()
        while(-not $proc.HasExited){[Windows.Forms.Application]::DoEvents();Start-Sleep -Milliseconds 80;if($watch.Elapsed.TotalSeconds-gt 120){$proc.Kill();throw 'Setup timed out. Check MySQL availability; rerunning setup is safe.'}}
        $result=$output.Result|ConvertFrom-Json;$status.Text=$result.message;if(-not $result.ok){throw $result.message}
    }catch{$status.Text=$_.Exception.Message}
    finally{$fields['password'].Clear();$state.Busy=$false;$test.Enabled=$true;$install.Enabled=$true;$progress.Style='Blocks'}
}
$test.Add_Click({& $execute $true});$install.Add_Click({& $execute $false})
if($PreviewImage){$form.ShowInTaskbar=$false;$form.Opacity=0;$form.Show();[Windows.Forms.Application]::DoEvents();$form.PerformLayout();$image=New-Object Drawing.Bitmap($form.Width,$form.Height);$form.DrawToBitmap($image,(New-Object Drawing.Rectangle(0,0,$form.Width,$form.Height)));$image.Save([IO.Path]::GetFullPath($PreviewImage),[Drawing.Imaging.ImageFormat]::Png);$image.Dispose();$form.Close();$form.Dispose();exit}
[void]$form.ShowDialog()
