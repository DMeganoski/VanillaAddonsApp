<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

class AddonModel extends Gdn_Model {
   const TYPE_PLUGIN = 1;
   const TYPE_THEME = 2;
   const TYPE_LANGUAGE = 4;
   const TYPE_APPLICATION = 5;

   protected $_AddonCache = array();

   public function __construct() {
      parent::__construct('Addon');
   }
   
   public function AddonQuery() {
      $this->SQL
         ->Select('a.*')
         ->Select('t.Label', '', 'Type')
         ->Select('v.AddonVersionID, v.File, v.Version, v.DateReviewed, v.TestedWith')
         ->Select('v.DateInserted', '', 'DateUploaded')
         ->Select('iu.Name', '', 'InsertName')
         ->From('Addon a')
         ->Join('AddonType t', 'a.AddonTypeID = t.AddonTypeID')
         ->Join('AddonVersion v', 'a.CurrentAddonVersionID = v.AddonVersionID', 'left')
         ->Join('User iu', 'a.InsertUserID = iu.UserID')
         ->Where('a.Visible', '1');
   }

      /**
    * Check an addon's file to extract the addon information out of it.
    *
    * @param string $Path The path to the file.
    * @param bool $Fix Whether or not to fix files that have been zipped incorrectly.
    * @return array An array of addon information.
    */
   public static function AnalyzeFile($Path, $Fix = FALSE, $ThrowError = TRUE) {
      $Result = array();

      // Extract the zip file so we can make sure it has appropriate information.
      $Zip = new ZipArchive();

      $ZipOpened = $Zip->open($Path);
      if ($ZipOpened !== TRUE) {
         throw new Exception(T('Could not open addon file. Addons must be zip files.'), 400);
      }

      $Entries = array();
      for ($i = 0; $i < $Zip->numFiles; $i++) {
         $Entries[] = $Zip->statIndex($i);
      }

      // Figure out which system files to delete.
      $Deletes = array();

      foreach ($Entries as $Index => $Entry) {
         $Name = $Entry['name'];
         $Delete = strpos($Name, '__MACOSX') !== FALSE
            | strpos($Name, '.DS_Store') !== FALSE
            | strpos($Name, 'thumbs.db') !== FALSE;

         if ($Delete) {
            $Deletes[] = $Entry;
            unset($Entries[$Index]);
         }
      }

      // Get a folder ready for checking the addon.
      $FolderPath = dirname($Path).'/'.basename($Path, '.zip').'/';
      if (file_exists($FolderPath))
         Gdn_FileSystem::RemoveFolder($FolderPath);

      // Figure out what kind of addon this is.
      $Root = '';
      $NewRoot = '';
      $Addon = FALSE;
      foreach ($Entries as $Entry) {
         $Name = '/'.ltrim($Entry['name'], '/');
         $Filename = basename($Name);
         $Folder = substr($Name, 0, -strlen($Filename));
         $NewRoot = '';

         // Check to see if the entry is a plugin file.
         if ($Filename == 'default.php' || StringEndsWith($Filename, '.plugin.php')) {
            if (count(explode('/', $Folder)) > 3) {
               // The file is too deep to be a plugin file.
               continue;
            }

            // This could be a plugin file, but we have to examine its info array.
            $Zip->extractTo($FolderPath, $Entry['name']);
            $FilePath = CombinePaths(array($FolderPath, $Name));
            $Info = self::ParseInfoArray($FilePath, 'PluginInfo');

            if (!is_array($Info) || !count($Info))
               continue;

            // Check to see if the info array conforms to a plugin spec.
            $Key = key($Info);
            $Info = $Info[$Key];
            $Root = trim($Folder, '/');

            $Valid = TRUE;

            // Make sure the key matches the folder name.
            if ($Root && strcasecmp($Root, $Key) != 0) {
               $Result[] = "$Name: The plugin's key is not the same as its folder name.";
               $Valid = FALSE;
            } else {
               $NewRoot = $Root;
            }

            if (!GetValue('Description', $Info)) {
               $Result[] = $Name.': '.sprintf(T('ValidateRequired'), T('Description'));
               $Valid = FALSE;
            }

            if (!GetValue('Version', $Info)) {
               $Result[] = $Name.': '.sprintf(T('ValidateRequired'), T('Version'));
               $Valid = FALSE;
            }

            if ($Valid) {
               // The plugin was confirmed.
               $Addon = array(
                   'AddonKey' => $Key,
                   'AddonTypeID' => self::TYPE_PLUGIN,
                   'Name' => GetValue('Name', $Info) ? $Info['Name'] : $Key,
                   'Description' => $Info['Description'],
                   'Version' => $Info['Version'],
                   'Path' => $Path);
               break;
            }
            continue;
         }

         // Check to see if the entry is an application file.
         if (StringEndsWith($Name, '/settings/about.php')) {
            if (count(explode('/', $Folder)) > 4) {
               $Result[] = "$Name: The application's info array was not in the correct location.";
               // The file is too deep to be a plugin file.
               continue;
            }

            // This could be a plugin file, but we have to examine its info array.
            $Zip->extractTo($FolderPath, $Entry['name']);
            $FilePath = CombinePaths(array($FolderPath, $Name));
            $Info = self::ParseInfoArray($FilePath, 'ApplicationInfo');

            if (!is_array($Info) || !count($Info)) {
               $Result[] = "$Name: The application's info array could not be parsed.";
               continue;
            }

            $Key = key($Info);
            $Info = $Info[$Key];
            $Root = trim(substr($Name, 0, -strlen('/settings/about.php')), '/');
            $Valid = TRUE;

            // Make sure the key matches the folder name.
            if ($Root && strcasecmp($Root, $Key) != 0) {
               $Result[] = "$Name: The application's key is not the same as its folder name.";
               $Valid = FALSE;
            } else {
               $NewRoot = $Root;
            }

            if (!GetValue('Description', $Info)) {
               $Result[] = $Name.': '.sprintf(T('ValidateRequired'), T('Description'));
               $Valid = FALSE;
            }

            if (!GetValue('Version', $Info)) {
               $Result[] = $Name.': '.sprintf(T('ValidateRequired'), T('Version'));
               $Valid = FALSE;
            }

            if ($Valid) {
               // The application was confirmed.
               $Addon = array(
                   'AddonKey' => $Key,
                   'AddonTypeID' => self::TYPE_APPLICATION,
                   'Name' => GetValue('Name', $Info) ? $Info['Name'] : $Key,
                   'Description' => $Info['Description'],
                   'Version' => $Info['Version'],
                   'Path' => $Path);
               break;
            }
            continue;
         }

         // Check to see if the entry is a theme file.
         if (StringEndsWith($Name, '/about.php')) {
            if (count(explode('/', $Folder)) > 3) {
               // The file is too deep to be a plugin file.
               continue;
            }

            // This could be a theme file, but we have to examine its info array.
            $Zip->extractTo($FolderPath, $Entry['name']);
            $FilePath = CombinePaths(array($FolderPath, $Name));
            $Info = self::ParseInfoArray($FilePath, 'ThemeInfo');

            if (!is_array($Info) || !count($Info))
               continue;

            $Key = key($Info);
            $Info = $Info[$Key];
            $Valid = TRUE;

            $Root = trim(substr($Name, 0, -strlen('/about.php')), '/');
            // Make sure the theme is at least one folder deep.
            if (strlen($Root) == 0) {
               $Result[] = $Name.': The theme must be in a folder.';
               $Valid = FALSE;
            }
            
            if (!GetValue('Description', $Info)) {
               $Result[] = $Name.': '.sprintf(T('ValidateRequired'), T('Description'));
               $Valid = FALSE;
            }

            if (!GetValue('Version', $Info)) {
               $Result[] = $Name.': '.sprintf(T('ValidateRequired'), T('Version'));
               $Valid = FALSE;
            }

            if ($Valid) {
               // The application was confirmed.
               $Addon = array(
                   'AddonKey' => $Key,
                   'AddonTypeID' => self::TYPE_THEME,
                   'Name' => GetValue('Name', $Info) ? $Info['Name'] : $Key,
                   'Description' => $Info['Description'],
                   'Version' => $Info['Version'],
                   'Path' => $Path);
               break;
            }  
         }
      }

      if ($Addon) {
         // Add a human readable version of the requirements.
         $Requirements = ArrayTranslate($Info, array('RequiredApplications' => 'Applications', 'RequiredPlugins' => 'Plugins', 'RequiredThemes' => 'Themes'));
         $RequirementsStr = '';
         foreach ($Requirements as $Type => $Requirement) {
            if (!is_array($Requirement))
               continue;
            $RequirementsStr .= '<dt>'.T($Type)."</dt>\n";

            $Reqs = array();
            foreach ($Requirement as $Name => $Version) {
               $Reqs[] = htmlspecialchars($Name.' '.$Version);
            }
            $RequirementsStr .= '<dd>'.implode(', ', $Reqs)."</dd>\n\n";
         }
         if ($RequirementsStr)
            $RequirementsStr = '<dl>'.$RequirementsStr.'</dl>';
         $Addon['Requirements'] = $RequirementsStr;

         $Addon['Checked'] = TRUE;
         

         $UploadsPath = PATH_ROOT.'/uploads/';
         if (StringBeginsWith($Addon['Path'], $UploadsPath)) {
            $Addon['File'] = substr($Addon['Path'], strlen($UploadsPath));
         }
         if ($Fix) {
            // Delete extraneous files.
            foreach ($Deletes as $Delete) {
               $Zip->deleteName($Delete['name']);
            }
         }
      }

      $Zip->close();

      if ($Addon) {
         $Addon['MD5'] = md5_file($Path);
         return $Addon;
      } else {
         if ($ThrowError) {
            $Msg = implode("\n", $Result);
            throw new Exception($Msg, 400);
         } else {
            return FALSE;
         }
      }
   }

   public function DeleteVersion($VersionID) {
      $this->SQL->Put('AddonVersion', array('Deleted' => 1), array('AddonVersionID' => $VersionID));
   }
   
   public function Get($Offset = '0', $Limit = '', $Wheres = '') {
      if ($Limit == '') 
         $Limit = Gdn::Config('Vanilla.Discussions.PerPage', 50);

      $Offset = !is_numeric($Offset) || $Offset < 0 ? 0 : $Offset;
      
      $this->AddonQuery();
      
      if (is_array($Wheres))
         $this->SQL->Where($Wheres);

      return $this->SQL
         ->Limit($Limit, $Offset)
         ->Get();
   }

   /*
    * @return Gdn_DataSet
    */
   public function GetWhere($Where = FALSE, $OrderFields = '', $OrderDirection = 'asc', $Limit = FALSE, $Offset = FALSE) {
      $this->AddonQuery();
      
      if ($Where !== FALSE)
         $this->SQL->Where($Where);

      if ($OrderFields != '')
         $this->SQL->OrderBy($OrderFields, $OrderDirection);

      if ($Limit !== FALSE) {
         if ($Offset == FALSE || $Offset < 0)
            $Offset = 0;

         $this->SQL->Limit($Limit, $Offset);
      }

      return $this->SQL->Get();
   }
   
   public function GetCount($Wheres = '') {
      if (!is_array($Wheres))
         $Wheres = array();
         
      $Wheres['a.Visible'] = '1';
      return $this->SQL
         ->Select('a.AddonID', 'count', 'CountAddons')
         ->From('Addon a')
         ->Join('AddonType t', 'a.AddonTypeID = t.AddonTypeID')
         ->Where($Wheres)
         ->Get()
         ->FirstRow()
         ->CountAddons;
   }

   /**
    * Get an addon by ID or key.
    *
    * @param int|array $AddonID The addon ID which can be one of the following:
    *  - int: The AddonID.
    *  - array: An array where the first element is the addon's key and the second element is the addon type id.
    * @param bool $GetVersions Whether or not to get an array of all of the addon's versions.
    * @return object The addon.
    */
   public function GetID($AddonID, $GetVersions = FALSE) {
      // Look for the addon in the cache.
      foreach ($this->_AddonCache as $CachedAddon) {
         if (is_array($AddonID) && $CachedAddon->Key == $AddonID[0] && $CachedAddon->Type == $AddonID[1]) {
            $Addon = $CachedAddon;
            break;
         } elseif (is_numeric($AddonID) && $CachedAddon->AddonID == $AddonID) {
            $Addon = $CachedAddon;
            break;
         }
      }

      if (isset($Addon)) {
         $Result = $Addon;
      } else {
         $this->AddonQuery();

         if (is_array($AddonID))
            $this->SQL->Where(array('a.AddonKey' => $AddonID[0], 'a.AddonTypeID' => $AddonID[1]));
         else
            $this->SQL->Where('a.AddonID', $AddonID);

         $Result = $this->SQL->Get()->FirstRow();
         if (!$Result)
            return FALSE;


         $this->SetFileUrl($Result);
         $this->_AddonCache[] = $Result;
      }

      if ($GetVersions && !isset($Result->Versions)) {
         $Versions = $this->SQL->GetWhere('AddonVersion', array('AddonID' => GetValue('AddonID', $Result), 'Deleted' => 0))->Result();
         usort($Versions, array($this, 'VersionCompare'));
         $Result->Versions = $Versions;
      }

      return $Result;
   }

   public function VersionCompare($A, $B) {
      return version_compare(GetValue('Version', $A), GetValue('Version', $B));
   }

   public function GetVersion($VersionID) {
      $Result = $this->SQL
         ->Select('a.*')
         ->Select('v.AddonVersionID, v.Version, v.File, v.MD5, v.Checked')
         ->From('Addon a')
         ->From('AddonVersion v', 'a.AddonID = v.AddonID')
         ->Where('v.AddonVersionID', $VersionID)
         ->Get()->FirstRow(DATASET_TYPE_ARRAY);
      return $Result;
   }

   /**
    * Offers a quick and dirty way of parsing an addon's info array without using eval().
    * @param string $Path The path to the info array.
    * @param string $Variable The name of variable containing the information.
    * @return array|false The info array or false if the file could not be parsed.
    */
   public static function ParseInfoArray($Path, $Variable) {
      $fp = fopen($Path, 'rb');
      $Lines = array();
      $InArray = FALSE;

      // Get all of the lines in the info array.
      while (($Line = fgets($fp)) !== FALSE) {
         // Remove comments from the line.
         $Line = preg_replace('`\s//.*$`', '', $Line);
         if (!$Line)
            continue;

         if (StringBeginsWith(trim($Line), '$'.trim($Variable, '$'))) {
            if (preg_match('`\[\s*[\'"](.+?)[\'"]\s*\]`', $Line, $Matches)) {
               $GlobalKey = $Matches[1];
               $InArray = TRUE;
            }
         } elseif ($InArray && StringEndsWith(trim($Line), ';')) {
            break;
         } elseif ($InArray) {
            $Lines[] = trim($Line);
         }
      }
      fclose($fp);

      if (count($Lines) == 0)
         return FALSE;

      // Parse the name/value information in the arrays.
      $Result = array();
      foreach ($Lines as $Line) {
         // Get the name from the line.
         if (!preg_match('`[\'"](.+?)[\'"]\s*=>`', $Line, $Matches) || !substr($Line, -1) == ',')
            continue;
         $Key = $Matches[1];

         // Strip the key from the line.
         $Line = trim(trim(substr(strstr($Line, '=>'), 2)), ',');

         if (strlen($Line) == 0)
            continue;

         $Value = NULL;
         if (is_numeric($Line))
            $Value = $Line;
         elseif (strcasecmp($Line, 'TRUE') == 0 || strcasecmp($Line, 'FALSE') == 0)
            $Value = $Line;
         elseif (in_array($Line[0], array('"', "'")) && substr($Line, -1) == $Line[0]) {
            $Quote = $Line[0];
            $Value = trim($Line, $Quote);
            $Value = str_replace('\\'.$Quote, $Quote, $Value);
         } elseif (StringBeginsWith($Line, 'array(') && substr($Line, -1) == ')') {
            // Parse the line's array.
            $Line = substr($Line, 6, strlen($Line) - 7);
            $Items = explode(',', $Line);
            $Array = array();
            foreach ($Items as $Item) {
               $SubItems = explode('=>', $Item);
               if (count($SubItems) == 1) {
                  $Array[] = trim(trim($SubItems[0]), '"\'');
               } elseif (count($SubItems) == 2) {
                  $SubKey = trim(trim($SubItems[0]), '"\'');
                  $SubValue = trim(trim($SubItems[1]), '"\'');
                  $Array[$SubKey] = $SubValue;
               }
            }
            $Value = $Array;
         }

         if ($Value != NULL) {
            $Result[$Key] = $Value;
         }
      }
      $Result = array($GlobalKey => $Result);
      return $Result;
   }

   public function SetFileUrl(&$Data) {
      if (!$Data)
         return;
      
      if (is_object($Data) || !isset($Data[0])) {
         $File = GetValue('File', $Data);
         $Url = Url("/uploads/File", TRUE);
         SetValue('FileUrl', $Data, $Url);
      }
      foreach ($Data as &$Row) {
         $File = GetValue('File', $Row);
         $Url = Url("/uploads/File", TRUE);
         SetValue('FileUrl', $Row, $Url);
      }
   }

   public function Save($Stub) {
      $Session = Gdn::Session();

      $this->DefineSchema();

      // Most of the values come from the file itself.
      if (isset($Stub['Path'])) {
         $Path = $Stub['Path'];
      } elseif (isset($Stub['File'])) {
         $Path = CombinePaths(array(PATH_UPLOADS, $Stub['File']));
      } else {
         if (!$Session->CheckPermission('Addons.Addon.Manage')) {
            // Only admins can modify plugin attributes without the file.
            $this->Validation->AddValidationResult('Filename', 'ValidateRequired');
            return FALSE;
         }
      }
      
      // Analyze and fix the file.
      if (isset($Path)) {
         try {
            $Addon = self::AnalyzeFile($Path, TRUE);
         } catch (Exception $Ex) {
            $Addon = FALSE;
            $this->Validation->AddValidationResult('File', '@'.$Ex->getMessage());
         }
         if (!is_array($Addon)) {
            $this->Validation->AddValidationResult('File', 'Could not analyze the addon file.');
            return FALSE;
         }
         $Addon = array_merge($Stub, $Addon);
      } else {
         $Addon = $Stub;
      }

      // Get an existing addon.
      if (isset($Addon['AddonID']))
         $CurrentAddon = $this->GetID($Addon['AddonID'], TRUE);
      elseif (isset($Addon['AddonKey']) && isset($Addon['AddonTypeID']))
         $CurrentAddon = $this->GetID(array($Addon['AddonKey'], $Addon['AddonTypeID']), TRUE);
      else
         $CurrentAddon = FALSE;

      $Insert = !$CurrentAddon;
      if ($Insert)
         $this->AddInsertFields ($Addon);

      if (!$this->Validate($Addon, $Insert)) {
         return FALSE;
      }

      // Search for the current version.
      $MaxVersion = FALSE;
      $CurrentVersion = FALSE;
      if ($CurrentAddon && isset($Addon['Version'])) {
         // Search for a current version.
         foreach ($CurrentAddon->Versions as $Index => $Version) {
            if (isset($Addon['AddonVersionID'])) {
               if ($Addon['AddonVersionID'] == $Version->AddonVersionID)
                  $CurrentVersion = $Version;
            } elseif (version_compare($Addon['Version'], $Version->Version) == 0) {
               $CurrentVersion = $Version;
            }

            // Only check for a current version if the version has been checked.
            if (!$Version->Checked)
               continue;

            if (!$MaxVersion || version_compare($MaxVersion->Version, $Version->Version, '>')) {
               $MaxVersion = $Version;
            }
         }
      }

      // Save the addon.
      $Fields = $this->FilterSchema($Addon);
      if ($Insert) {
         $AddonID = $this->SQL->Insert($this->Name, $Fields);
      } else {
         $this->AddUpdateFields($Fields);
         $AddonID = GetValue('AddonID', $CurrentAddon);

         // Only save the addon if it is the current version.
         if (!$MaxVersion || version_compare($Addon['Version'], $MaxVersion->Version, '>='))
            $this->SQL->Put($this->Name, $Fields, array('AddonID' => $AddonID));
         else
            $this->SQL->Reset();
      }

      // Save the version.
      if ($AddonID && isset($Path)) {
         $Addon['AddonID'] = $AddonID;
         if (!StringBeginsWith($Path, PATH_UPLOADS.DS)) {
            // The addon must be copied into the uploads folder.
            $NewPath = PATH_UPLOADS.'/addons/'.basename($Path);
            rename($Path, $NewPath);
            $Path = $NewPath;
         }
         $File = substr($Path, strlen(PATH_UPLOADS.DS));
         $Addon['File'] = $File;

         if ($CurrentVersion) {
            $Addon['AddonVersionID'] = GetValue('AddonVersionID', $CurrentVersion);
         }

         // Insert or update the version.
         $VersionModel = new Gdn_Model('AddonVersion');
         $AddonVersionID = $VersionModel->Save($Addon);
         $this->Validation->AddValidationResult($VersionModel->ValidationResults());

         if (!$AddonVersionID) {
            return FALSE;
         }

         // Update the current version in the addon.
         if (!$MaxVersion || version_compare($Addon['Version'], $MaxVersion->Version, '>=')) {
            $this->SQL->Put($this->Name,
               array('CurrentAddonVersionID' => $AddonVersionID),
               array('AddonID' => $AddonID));
         }
      }

      return $AddonID;
   }
   
   public function SaveBak($FormPostValues, $FileName = '') {
      $Session = Gdn::Session();
      
      // Define the primary key in this model's table.
      $this->DefineSchema();

      if (array_key_exists('AddonKey', $FormPostValues))
         $this->Validation->ApplyRule('AddonKey', 'Required');
      
      // Add & apply any extra validation rules:
      if (array_key_exists('Description', $FormPostValues))
         $this->Validation->ApplyRule('Description', 'Required');

      if (array_key_exists('Version', $FormPostValues))
         $this->Validation->ApplyRule('Version', 'Required');
/*
      if (array_key_exists('TestedWith', $FormPostValues))
         $this->Validation->ApplyRule('TestedWith', 'Required');
*/      
      // Get the ID from the form so we know if we are inserting or updating.
      $AddonID = ArrayValue('AddonID', $FormPostValues, '');
      $Insert = $AddonID == '' ? TRUE : FALSE;
      
      if ($Insert) {
         if(!array_key_exists('Vanilla2', $FormPostValues))
            $FormPostValues['Vanilla2'] = '0';
         
         unset($FormPostValues['AddonID']);
         $this->AddInsertFields($FormPostValues);
      } else if (!array_key_exists('Vanilla2', $FormPostValues)) {
         $Tmp = $this->GetID($AddonID);
         $FormPostValues['Vanilla2'] = $Tmp->Vanilla2 ? '1' : '0';
      }
      $this->AddUpdateFields($FormPostValues);
      // Validate the form posted values
      if ($this->Validate($FormPostValues, $Insert)) {
         $Fields = $this->Validation->SchemaValidationFields(); // All fields on the form that relate to the schema
         $AddonID = intval(ArrayValue('AddonID', $Fields, 0));
         $Fields = RemoveKeyFromArray($Fields, 'AddonID'); // Remove the primary key from the fields for saving
         $Addon = FALSE;
         $Activity = 'EditAddon';
         if ($AddonID > 0) {
            $this->SQL->Put($this->Name, $Fields, array($this->PrimaryKey => $AddonID));
         } else {
            $AddonID = $this->SQL->Insert($this->Name, $Fields);
            $Activity = 'AddAddon';
         }
         // Save the version
         if ($AddonID > 0 && $FileName != '') {
            // Save the addon file & version
            $AddonVersionModel = new Gdn_Model('AddonVersion');
            $AddonVersionID = $AddonVersionModel->Save(array(
               'AddonID' => $AddonID,
               'File' => $FileName,
               'Version' => ArrayValue('Version', $FormPostValues, ''),
               'TestedWith' => ArrayValue('TestedWith', $FormPostValues, 'Empty'),
               'DateInserted' => Gdn_Format::ToDateTime()
            ));
            // Mark the new addon file & version as the current version
            $this->SQL->Put($this->Name, array('CurrentAddonVersionID' => $AddonVersionID), array($this->PrimaryKey => $AddonID));
         }
         
         if ($AddonID > 0) {
            $Addon = $this->GetID($AddonID);

            // Record Activity
            AddActivity(
               $Session->UserID,
               $Activity,
               '',
               '',
               '/addon/'.$AddonID.'/'.Gdn_Format::Url($Addon->Name)
            );
         }
      }
      if (!is_numeric($AddonID))
         $AddonID = FALSE;
         
      return count($this->ValidationResults()) > 0 ? FALSE : $AddonID;
   }
   
   public function SetProperty($AddonID, $Property, $ForceValue = FALSE) {
      if ($ForceValue !== FALSE) {
         $Value = $ForceValue;
      } else {
         $Value = '1';
         $Addon = $this->GetID($AddonID);
         $Value = ($Addon->$Property == '1' ? '0' : '1');
      }
      $this->SQL
         ->Update('Addon')
         ->Set($Property, $Value)
         ->Where('AddonID', $AddonID)
         ->Put();
      return $Value;
   }

   public function Validate($Post, $Insert) {
      if ($Insert || isset($Post['AddonKey']))
         $this->Validation->ApplyRule('AddonKey', 'Required');
      if ($Insert || isset($Post['Description']))
         $this->Validation->ApplyRule('Description', 'Required');

      if ($Insert || isset($Post['Version'])) {
         $this->Validation->ApplyRule('Version', 'Required');
         $this->Validation->ApplyRule('Version', 'Version');
      }

      parent::Validate($Post, $Insert);

      // Validate against an existing addon.
      if ($AddonID = GetValue('AddonID', $Post)) {
         $CurrentAddon = $this->GetID($AddonID, TRUE);
         if ($CurrentAddon) {
            if (GetValue('AddonKey', $CurrentAddon) && isset($Post['AddonKey']) && GetValue('AddonKey', $Post) != GetValue('AddonKey', $CurrentAddon))
               $this->Validation->AddValidationResult('AddonKey', '@'.sprintf(T('The addon\'s key cannot be changed. The uploaded file has a key of <b>%s</b>, but it must be <b>%s</b>.'), GetValue('AddonKey', $Post), GetValue('AddonKey', $CurrentAddon)));
            else {
               // Make sure this version doesn't match.
               foreach ($CurrentAddon->Versions as $Version) {
                  if ($Version->Deleted)
                     continue;

                  if (version_compare(GetValue('Version', $Version), GetValue('Version', $Post)) == 0) {
                     // This version matches a previous version.
                     if (GetValue('Checked', $Version) && GetValue('MD5', $Version) != GetValue('MD5', $Post))
                        $this->Validation->AddValidationResult('Version', '@'.sprintf(T('Version %s of this addon already exists.'), GetValue('Version', $Version)));
                  }
               }
            }
         }
      }

      // Make sure there isn't another addon with the same key as this one.
      if (ValidateRequired(GetValue('AddonKey', $Post))) {
         $CountSame = $this->GetCount(array('AddonKey' => $Post['AddonKey'], 'AddonID <>' => GetValue('AddonID', $Post), 'a.AddonTypeID' => GetValue('AddonTypeID', $Post)));
         if ($CountSame > 0) {
            $this->Validation->AddValidationResult('AddonKey', '@'.sprintf(T('The addon key %s is already taken.'), $Post['AddonKey']));
         }
      }

      return count($this->Validation->Results()) == 0;
   }
      
   public function Delete($AddonID) {
      $this->SetProperty($AddonID, 'Visible', '0');
   }

   public function UpdateCurrentVersion($AddonID) {
      $Addon = $this->GetID($AddonID, TRUE);

      $MaxVersion = FALSE;
      foreach ($Addon->Versions as $Version) {
         if (!$Version->Checked || !$Version->Deleted)
            continue;
         if (!$MaxVersion || version_compare($Version->Version, $MaxVersion->Version, '>')) {
            $MaxVersion = $Version;
         }
      }
      if ($MaxVersion) {
         $this->SQL->History()->Put('Addon', array('CurrentAddonVersionID' => $MaxVersion->Version), array('AddonID' => $AddonID));
      }
   }
}