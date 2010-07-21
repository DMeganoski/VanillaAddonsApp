<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/


class AddonsHooks implements Gdn_IPlugin {
   public function Controller_Event_Handler($Sender) {
      // Do something
   }
   
   // Write information about addons to the discussion if it is related to an addon
   public function DiscussionController_BeforeCommentBody_Handler($Sender) {
      $Discussion = GetValue('Object', $Sender->EventArguments);
      $AddonID = GetValue('AddonID', $Discussion);
      if (GetValue('Type', $Sender->EventArguments) == 'Discussion' && is_numeric($AddonID) && $AddonID > 0) {
         $Data = Gdn::Database()->SQL()->Select('Name')->From('Addon')->Where('AddonID', $AddonID)->Get()->FirstRow();
         if ($Data) {
            echo '<div class="Warning">'.sprintf(T('This discussion is related to the %s addon.'), Anchor($Data->Name, 'addons/addon/'.$AddonID.'/'.Gdn_Format::Url($Data->Name))).'</div>';
         }
      }
   }
   
   // Pass the addonid to the form
   public function PostController_Render_Before($Sender) {
      $AddonID = GetIncomingValue('AddonID');
      if ($AddonID > 0 && is_object($Sender->Form))
         $Sender->Form->AddHidden('AddonID', $AddonID);
   }
   
   // Make sure to use the AddonID when saving discussions if present in the url
   public function DiscussionModel_BeforeSaveDiscussion_Handler($Sender) {
      $AddonID = GetIncomingValue('AddonID');
      if (is_numeric($AddonID) && $AddonID > 0) {
         $FormPostValues = GetValue('FormPostValues', $Sender->EventArguments);
         $FormPostValues['AddonID'] = $AddonID;
         $Sender->EventArguments['FormPostValues'] = $FormPostValues;
      }
   }
   
   // Make sure that all translations are in the GDN_Translation table for the "source" language
   public function Gdn_Locale_BeforeTranslate_Handler(&$Sender) {
      $Code = ArrayValue('Code', $Sender->EventArguments, '');
      if ($Code != '' && !in_array($Code, $this->GetTranslations())) {
         $Session = Gdn::Session();
         // If the code wasn't in the source list, insert it
         $Database = Gdn::Database();
         $Database->SQL()->Replace('Translation', array(
            'Value' => $Code,
            'UserLanguageID' => 1,
            'Application' => $this->_EnabledApplication(),
            'InsertUserID' => $Session->UserID,
            'DateInserted' => Gdn_Format::ToDateTime(),
            'UpdateUserID' => $Session->UserID,
            'DateUpdated' => Gdn_Format::ToDateTime()
            ), array('Value' => $Code));
      }
   }

   private $_EnabledApplication = 'Vanilla';   
   public function Gdn_Dispatcher_AfterEnabledApplication_Handler(&$Sender) {
      $this->_EnabledApplication = ArrayValue('EnabledApplication', $Sender->EventArguments, 'Vanilla'); // Defaults to "Vanilla"
   }
   private function _EnabledApplication() {
      return $this->_EnabledApplication;
   }
   
   private $_Translations = FALSE;
   private function GetTranslations() {
      if (!is_array($this->_Translations)) {
         $TranslationModel = new Gdn_Model('Translation');
         $Translations = $TranslationModel->GetWhere(array('UserLanguageID' => 1));
         $this->_Translations = array();
         foreach ($Translations as $Translation) {
            $this->_Translations[] = $Translation->Value;
         }
      }
      return $this->_Translations;
   }
   
   public function ProfileController_AfterPreferencesDefined_Handler(&$Sender) {
      $Sender->Preferences['Email Notifications']['Email.AddonComment'] = T('Notify me when people comment on my addons.');
      $Sender->Preferences['Email Notifications']['Email.AddonCommentMention'] = T('Notify me when people mention me in addon comments.');
   }
   

   public function Setup() {
      $Database = Gdn::Database();
      $Config = Gdn::Factory(Gdn::AliasConfig);
      $Drop = C('Addons.Version') === FALSE ? TRUE : FALSE;
      $Explicit = TRUE;
      $Validation = new Gdn_Validation(); // This is going to be needed by structure.php to validate permission names
      include(PATH_APPLICATIONS . DS . 'addons' . DS . 'settings' . DS . 'structure.php');

      $ApplicationInfo = array();
      include(CombinePaths(array(PATH_APPLICATIONS . DS . 'addons' . DS . 'settings' . DS . 'about.php')));
      $Version = ArrayValue('Version', ArrayValue('Addons', $ApplicationInfo, array()), 'Undefined');
      SaveToConfig('Addons.Version', $Version);
   }
}