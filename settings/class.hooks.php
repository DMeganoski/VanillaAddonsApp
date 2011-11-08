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
   /**
    * DiscussionModel Get() additions.
    *
    * Note: AddonID is manually added to DiscussionModel before Get() in AddonController.
    */
//   public function DiscussionModel_BeforeGet_Handler($Sender) {
//      $AddonID = GetValue('AddonID', $Sender);
//      if (is_numeric($AddonID) && $AddonID > 0) {
//         // Filter discussion list to a particular AddonID if present in model
//         $Sender->SQL->Where('AddonID', $AddonID);
//      }
//      else { 
//         // Make Addon name available on discussions list
//         $Sender->SQL->Select('ad.Name', '', 'AddonName')
//            ->Join('Addon ad', 'd.AddonID = ad.AddonID', 'left');
//      }
//   }
   
   /**
    * Hook for discussion prefixes in /discussions.
    */
   public function Base_BeforeDiscussionMeta_Handler($Sender, $Args) {
      if (Gdn::Controller()->ControllerName == 'addoncontroller')
         return;
      $this->AddonDiscussionPrefix($Args['Discussion']);
   }
   
   /**
    * Add prefix to the passed controller's discussion names when they are re: an addon.
    *
    * Ex: [AddonName] Discussion original name
    */
   public function AddonDiscussionPrefix($Discussion) {
      $Addon = GetValue('Addon', $Discussion);
      if ($Addon) {
         $Slug = AddonModel::Slug($Addon, FALSE);
         $Url = "/addon/$Slug";
         $AddonName = GetValue('Name', $Addon);
         echo ' '.Wrap(Anchor(Gdn_Format::Html($AddonName), $Url), 'span', array('class' => 'Tag Tag-Addon')).' ';
      }
   }
   
   // Write information about addons to the discussion if it is related to an addon
   public function DiscussionController_BeforeCommentBody_Handler($Sender) {
      $Discussion = GetValue('Object', $Sender->EventArguments);
      $AddonID = GetValue('AddonID', $Discussion);
      if (GetValue('Type', $Sender->EventArguments) == 'Discussion' && is_numeric($AddonID) && $AddonID > 0) {
         $Data = Gdn::Database()->SQL()->Select('Name')->From('Addon')->Where('AddonID', $AddonID)->Get()->FirstRow();
         if ($Data) {
            echo '<div class="Warning">'.sprintf(T('This discussion is related to the %s addon.'), Anchor($Data->Name, 'addon/'.$AddonID.'/'.Gdn_Format::Url($Data->Name))).'</div>';
         }
      }
   }
   
   /**
    *
    * @param DiscussionsController $Sender
    */
   public function DiscussionModel_AfterAddColumns_Handler($Sender, $Args) {
      AddonModel::JoinAddons($Args['Data'], 'AddonID', array('Name', 'Icon', 'AddonKey', 'AddonTypeID', 'Checked'));
   }
   
   public function DiscussionsController_BeforeDiscussionContent_Handler($Sender, $Args) {
      static $AddonModel = NULL;
      if (!$AddonModel) $AddonModel = new AddonModel();
      
      $Discussion = $Args['Discussion'];
      $Addon = GetValue('Addon', $Discussion);
      if ($Addon) {
         $Slug = AddonModel::Slug($Addon, FALSE);
         $Url = "/addon/$Slug";
//         if ($Addon['Icon']) {
//            echo Anchor(Img(Gdn_Upload::Url($Addon['Icon'])), $Url, array('class' => 'Addon-Icon Author'));
//         } else {
//            echo Wrap(Anchor('Addon', $Url), 'span', array('class' => 'Tag Tag-Addon'));
//         }
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
   
   public function DiscussionModel_BeforeNotification_Handler($Sender, $Args) {
      $Discussion = $Args['Discussion'];
      if (!GetValue('AddonID', $Discussion))
         return;
      
      $AddonModel = new AddonModel();
      $Addon = $AddonModel->GetID($Discussion['AddonID'], DATASET_TYPE_ARRAY);
      
      if (GetValue('InsertUserID', $Addon) == Gdn::Session()->UserID)
         return;
      
      $ActivityModel = $Args['ActivityModel'];
      $ActivityID = $ActivityModel->Add(
         $Discussion['InsertUserID'],
         'AddonComment',
         Gdn_Format::Text(Gdn_Format::To($Discussion['Body'], $Discussion['Format'])),
         $Addon['InsertUserID'],
         '',
         '/discussion/'.$Discussion['DiscussionID'].'/'.Gdn_Format::Url($Discussion['Name']),
         'QueueOnly'
      );
   }
   
   // Make sure that all translations are in the GDN_Translation table for the "source" language
   public function Gdn_Locale_BeforeTranslate_Handler($Sender) {
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
   public function Gdn_Dispatcher_AfterEnabledApplication_Handler($Sender) {
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
   
   public function ProfileController_AfterPreferencesDefined_Handler($Sender) {
      $Sender->Preferences['Notifications']['Email.AddonComment'] = T('Notify me when people comment on my addons.');
   }
   
   /**
    * Adds 'Addons' tab to profiles and adds CSS & JS files to their head.
    * 
    * @since 2.0.0
    * @package Vanilla
    * 
    * @param object $Sender ProfileController.
    */ 
   public function ProfileController_AddProfileTabs_Handler($Sender) {
      if (is_object($Sender->User) && $Sender->User->UserID > 0) {
         $Sender->AddProfileTab(T('Addons'), 'profile/addons/'.$Sender->User->UserID.'/'.urlencode($Sender->User->Name));
         // Add the discussion tab's CSS and Javascript
         $Sender->AddCssFile('profile.css', 'addons');
         $Sender->AddJsFile('addons.js');
      }
   }
   /**
	 * Creates addons tab ProfileController.
	 * 
    * @since 2.0.0
    * @package Vanilla
	 *
	 * @param object $Sender ProfileController.
	 */
   public function ProfileController_Addons_Create($Sender) {
      $UserReference = ArrayValue(0, $Sender->RequestArgs, '');
		$Username = ArrayValue(1, $Sender->RequestArgs, '');
      // $Offset = ArrayValue(2, $Sender->RequestArgs, 0);
      // Tell the ProfileController what tab to load
		$Sender->GetUserInfo($UserReference, $Username);
      $Sender->SetTabView('Addons', 'Profile', 'Addon', 'Addons');
      
      // Load the data for the requested tab.
      // if (!is_numeric($Offset) || $Offset < 0)
      //   $Offset = 0;
      
      $Offset = 0;
      $Limit = 100;
      $AddonModel = new AddonModel();
		$ResultSet = $AddonModel->GetWhere(array('UserID' => $Sender->User->UserID), 'DateUpdated', 'desc', $Limit, $Offset);
		$Sender->SetData('Addons', $ResultSet);
		$NumResults = $AddonModel->GetCount(array('InsertUserID' => $Sender->User->UserID));
      
      // Set the HandlerType back to normal on the profilecontroller so that it fetches it's own views
      $Sender->HandlerType = HANDLER_TYPE_NORMAL;
      
      // Render the ProfileController
      $Sender->Render();
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