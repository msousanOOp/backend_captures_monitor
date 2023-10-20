; Script generated by the Inno Setup Script Wizard.
; SEE THE DOCUMENTATION FOR DETAILS ON CREATING INNO SETUP SCRIPT FILES!

#define MyAppName "SnoopAgent"
#define MyAppVersion "1.0.1"
#define MyAppPublisher "DBSNOOP"
#define MyAppURL "https://www.dbsnoop.com/"
#define MyAppExeName "install.exe"
#define MyAppAssocName MyAppName + ""
#define MyAppAssocExt ".exe"
#define MyAppAssocKey StringChange(MyAppAssocName, " ", "") + MyAppAssocExt

[Setup]
; NOTE: The value of AppId uniquely identifies this application. Do not use the same AppId value in installers for other applications.
; (To generate a new GUID, click Tools | Generate GUID inside the IDE.)
AppId={{2D61E24D-2AF8-4A27-9091-FD2913DDC096}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
;AppVerName={#MyAppName} {#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
DefaultDirName={autopf}\{#MyAppName}
DisableDirPage=yes
ChangesAssociations=yes
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=yes
; Uncomment the following line to run in non administrative install mode (install for current user only.)
;PrivilegesRequired=lowest
PrivilegesRequiredOverridesAllowed=dialog
OutputBaseFilename=installer
SetupIconFile=C:\Users\Rodrigo Danieli\Documents\Projetos\dbsnOOp\Code\APP\Frontend Nuxt\static\favicon.ico
Compression=lzma
SolidCompression=yes
WizardStyle=modern
AlwaysRestart=yes


[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Files]
Source: "C:\Users\Rodrigo Danieli\Documents\Projetos\dbsnOOp\Code\Captures\build_windows\{#MyAppExeName}"; DestDir: "{app}"; Flags: ignoreversion
Source: "C:\Users\Rodrigo Danieli\Documents\Projetos\dbsnOOp\Code\Captures\build_windows\files\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs
; NOTE: Don't use "Flags: ignoreversion" on any shared system files


[Registry]
Root: HKA; Subkey: "Software\Classes\{#MyAppAssocExt}\OpenWithProgids"; ValueType: string; ValueName: "{#MyAppAssocKey}"; ValueData: ""; Flags: uninsdeletevalue
Root: HKA; Subkey: "Software\Classes\{#MyAppAssocKey}"; ValueType: string; ValueName: ""; ValueData: "{#MyAppAssocName}"; Flags: uninsdeletekey
Root: HKA; Subkey: "Software\Classes\{#MyAppAssocKey}\DefaultIcon"; ValueType: string; ValueName: ""; ValueData: "{app}\{#MyAppExeName},0"
Root: HKA; Subkey: "Software\Classes\{#MyAppAssocKey}\shell\open\command"; ValueType: string; ValueName: ""; ValueData: """{app}\{#MyAppExeName}"" ""%1"""
Root: HKA; Subkey: "Software\Classes\Applications\{#MyAppExeName}\SupportedTypes"; ValueType: string; ValueName: ".myp"; ValueData: ""

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"
Name: "{group}\{cm:UninstallProgram,{#MyAppName}}"; Filename: "{uninstallexe}"



[Code]
var     
  UrlPage: TInputQueryWizardPage;
  InstallPage: TWizardPage; 
  Host: String;  
  Key: String;
  AppDir: String; 

function CallWeb(Url : String; Method: String; Params : String): Variant;
var     
  WinHttpReq: Variant; 
begin      
    WinHttpReq := CreateOleObject('WinHttp.WinHttpRequest.5.1');
    WinHttpReq.Open(Method, Url, False);     
    WinHttpReq.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    WinHttpReq.Send(Params);       
      
    Result := WinHttpReq  
end;

function VerifyHost(Page: TWizardPage): Boolean;
var
  Request1: Variant; 
  Request2: Variant; 
  Url : String;
begin


  Url := 'https://' + Trim(UrlPage.Values[0])+'/';
  Result := False;     
  try 
    if UrlPage.Values[0] <> '' then
    begin 
      Request1 := CallWeb(Url + 'ping', 'GET', '');           
      if Request1.Status = 200 then
      begin                      
        Request2 := CallWeb(Url + 'worker/is_valid_key', 'POST', 'key='+Trim(UrlPage.Values[1]));  
        
        {Log('HTTP: ' + Request2.ResponseText);       }  
        if Request2.Status = 200 then
        begin      
          Host := Url;
          Key :=  Trim(UrlPage.Values[1]);
          Result := True;
        end
        else
        begin
          // Trate aqui outras respostas HTTP (por exemplo, 404 - Not Found)
          MsgBox('Invalid Key!', mbError, MB_OK);
        end;
        end
      else
      begin
        MsgBox('Invalid Server Pool API Url!', mbError, MB_OK);
      end;
      end;
    except
      begin
        MsgBox('HTTP Request Error: ', mbError, MB_OK);
      end;
  end;
end;  

procedure InstallSys(Sender: TWizardPage);
var
  ResultCode: Integer; 
  PhpExec: string;  
  ScriptExec: string; 
  Script2Exec: string;  
begin

  AppDir := ExpandConstant('{app}') ; 

  PhpExec := 'php\php.exe'; 


  ScriptExec := 'source\helpers\EasyRegister.php ' + Host + ' ' + Key; 
  
  //Configura o Diretorio do Certificado SSL para o cUrl
  ShellExec('', PhpExec , 'source\helpers\ConfigCertPem.php',AppDir, SW_SHOW, ewWaitUntilTerminated, ResultCode);
  
  Sleep(3000);
  
  //Cadastra o coletor na plataforma
  if ShellExec('', PhpExec , ScriptExec, AppDir, SW_SHOW, ewWaitUntilTerminated, ResultCode) then
    begin 
      Script2Exec := 'create "Dbsnoop Agent" binpath= "'+AppDir+'\run.exe" start= auto';
      ShellExec('','sc.exe' , Script2Exec, AppDir, SW_SHOW, ewWaitUntilIdle, ResultCode);       
    end
    else begin                 
      MsgBox('N�o foi poss�vel finalizar a instala��o.', mbError, MB_OK);
    end;
end;

procedure InitializeWizard; 
begin   

  UrlPage := CreateInputQueryPage(wpInfoBefore, 'ServerPool API connection', 'Please, entry the server pool api URL and access KEY', '');
  UrlPage.Add('Url:', False);
  UrlPage.Add('KEY:', False);  
  UrlPage.OnNextButtonClick := @VerifyHost;  
  
  InstallPage := CreateCustomPage(wpInfoAfter,'Install API','Instalador');
  InstallPage.OnActivate := @InstallSys;

end;