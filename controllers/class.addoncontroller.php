<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

/**
 * MessagesController handles displaying lists of conversations and conversation messages.
 */
class AddonController extends AddonsController {   
   public $Uses = array('Form', 'AddonModel', 'AddonCommentModel');
	public $Filter = 'all';
	public $Sort = 'recent';
	public $Version = '0'; // The version of Vanilla to filter to (0 is no filter)

   /**
    * @var AddonModel
    */
   public $AddonModel;
   
   public function Initialize() {
      parent::Initialize();
      if ($this->Head) {
         $this->AddJsFile('jquery.js');
         $this->AddJsFile('jquery.livequery.js');
         $this->AddJsFile('jquery.form.js');
         $this->AddJsFile('jquery.popup.js');
         $this->AddJsFile('jquery.gardenhandleajaxform.js');
         $this->AddJsFile('global.js');
      }
   }

   /**
    * Home Page
    */
   public function Index($ID = '') {
      if ($ID != '') {
         $Addon = $this->AddonModel->GetSlug($ID, TRUE);
         if (!is_array($Addon)) {
            $this->View = 'NotFound';
         } else {
            $AddonID = $Addon['AddonID'];
            $this->SetData($Addon);

            if ($MaxVersion) {
               $this->SetData('CurrentVersion', GetValue('Version', $MaxVersion));

            }

            $this->AddCssFile('plugins/Voting/design/voting.css');
            $this->AddCssFile('popup.css');
            $this->AddCssFile('fancyzoom.css');
            $this->AddJsFile('fancyzoom.js');
   			$this->AddJsFile('/js/library/jquery.gardenmorepager.js');
            $this->AddJsFile('addon.js');
            $this->AddJsFile('plugins/Voting/voting.js');
            $PictureModel = new Gdn_Model('AddonPicture');
            $this->PictureData = $PictureModel->GetWhere(array('AddonID' => $AddonID));
				$DiscussionModel = new DiscussionModel();
				$DiscussionModel->AddonID = $AddonID; // Let the model know we want to filter to a particular addon (we then hook into the model in the addons hooks file).
				$this->DiscussionData = $DiscussionModel->Get(0, 50);
            
            $this->View = 'addon';
				$this->Title($this->Data('Name').' '.$this->Data('Version').' by '.$this->Data('InsertName'));

            // Set the canonical url.
            $this->CanonicalUrl(Url('/addon/'.AddonModel::Slug($Addon, FALSE), TRUE));
         }
      } else {
			$this->View = 'browse';
			$this->Browse();
			return;
		/*
         $this->ApprovedData = $this->AddonModel->GetWhere(array('DateReviewed is not null' => ''), 'DateUpdated', 'desc', 5);
         $ApprovedIDs = ConsolidateArrayValuesByKey($this->ApprovedData->ResultArray(), 'AddonID');
         if (count($ApprovedIDs) > 0)
            $this->AddonModel->SQL->WhereNotIn('a.AddonID', $ApprovedIDs);
            
         $this->NewData = $this->AddonModel->GetWhere(FALSE, 'DateUpdated', 'desc', 5);
		*/
      }
  		$this->AddModule('AddonHelpModule');
      $this->SetData('_Types', AddonModel::$Types);
      $this->SetData('_TypesPlural', AddonModel::$TypesPlural);
      
		$this->Render();
   }

   public function Add() {
      $this->Permission('Addons.Addon.Add');
      $this->AddJsFile('/js/library/jquery.autogrow.js');
      $this->AddJsFile('forms.js');

      $this->Form->SetModel($this->AddonModel);

      if ($this->Form->AuthenticatedPostBack()) {
         $Upload = new Gdn_Upload();
         $Upload->AllowFileExtension(NULL);
         $Upload->AllowFileExtension('zip');
         try {
            // Validate the upload
            $TmpFile = $Upload->ValidateUpload('File');
            $Extension = pathinfo($Upload->GetUploadedFileName(), PATHINFO_EXTENSION);

            // Generate the target file name
            $TargetFile = $Upload->GenerateTargetName('addons', $Extension);
            $FileBaseName = pathinfo($TargetFile, PATHINFO_BASENAME);

            // Save the uploaded file
            $Upload->SaveAs(
               $TmpFile,
               $TargetFile
            );
            $Path = $Upload->CopyLocal($TargetFile);
            $this->Form->SetFormValue('Path', $Path);
         } catch (Exception $ex) {
            $this->Form->AddError($ex->getMessage());
         }

         // If there were no errors, save the addon
         if ($this->Form->ErrorCount() == 0) {
            // Set some additional values to save.
            $this->Form->SetFormValue('Vanilla2', TRUE);

            // Save the addon
            $AddonID = $this->Form->Save();
            if ($AddonID !== FALSE) {
               $Addon = $this->AddonModel->GetID($AddonID);

               // Redirect to the new addon
               Redirect("addon/".AddonModel::Slug($Addon, FALSE));
            }
         } else {
            if (isset($TargetFile) && file_exists($TargetFile))
               unlink($TargetFile);
         }
      }

      $this->Render();
   }
   
   /**
    * Backup version of add for Vanilla 1 addons.
    */
   public function AddV1() {
		$this->Permission('Addons.Addon.Add');
		$this->AddJsFile('/js/library/jquery.autogrow.js');
		$this->AddJsFile('forms.js');
      
      $this->Form->SetModel($this->AddonModel);
      $AddonTypeModel = new Gdn_Model('AddonType');
      $this->TypeData = $AddonTypeModel->GetWhere(array('Visible' => '1', 'Label <>' => 'Core'));
      
      if ($this->Form->AuthenticatedPostBack()) {
         $Upload = new Gdn_Upload();
         $Upload->AllowFileExtension(NULL);
         $Upload->AllowFileExtension('zip');
         try {
            // Validate the upload
            $TmpFile = $Upload->ValidateUpload('File');
            $Extension = pathinfo($Upload->GetUploadedFileName(), PATHINFO_EXTENSION);
            
            // Generate the target file name
            $TargetFile = $Upload->GenerateTargetName('addons', $Extension);
            $FileBaseName = pathinfo($TargetFile, PATHINFO_BASENAME);
            
            // Save the uploaded file
            $Upload->SaveAs(
               $TmpFile,
               $TargetFile
            );
            $Path = $Upload->CopyLocal($TargetFile);
         } catch (Exception $ex) {
            $this->Form->AddError($ex->getMessage());
         }
         // If there were no errors, save the addon
         if ($this->Form->ErrorCount() == 0) {
            // Save the addon
            $this->Form->SetFormValue('File', "addons/$FileBaseName");
            $this->Form->SetFormValue('Vanilla2', FALSE);
            $AddonID = $this->Form->Save(TRUE);
            if ($AddonID !== FALSE) {
               // Redirect to the new addon
               $Name = $this->Form->GetFormValue('Name', '');
               Redirect('addon/'.$AddonID.'/'.Gdn_Format::Url($Name));
            } else {
               // Delete an erroneous file.
               if (file_exists($TargetFile))
                  unlink($TargetFile);
            }
         }
      }
      $this->Render();      
   }

   public function Check($AddonID, $SaveVersionID = FALSE) {
      $this->Permission('Addons.Addon.Manage');

      if ($SaveVersionID !== FALSE) {
         // Get the version data.
         $Version = $this->AddonModel->SQL->GetWhere('AddonVersion', array('AddonVersionID' => $SaveVersionID))->FirstRow(DATASET_TYPE_ARRAY);

         $this->AddonModel->Save($Version);
         $this->Form->SetValidationResults($this->AddonModel->ValidationResults());
      }

      $Addon = $this->AddonModel->GetID($AddonID, TRUE);
      $AddonTypes = Gdn::SQL()->Get('AddonType')->ResultArray();
      $AddonTypes = Gdn_DataSet::Index($AddonTypes, 'AddonTypeID');

      if (!$Addon)
         throw NotFoundException('Addon');

      // Get the data for the most recent version of the addon.
      $Path = PATH_LOCAL_UPLOADS.'/'.$Addon['File'];
      
      $AddonData = ArrayTranslate((array)$Addon, array('AddonID', 'AddonKey', 'Name', 'Type', 'Description', 'Requirements', 'Checked'));
      try {
         $FileAddonData = UpdateModel::AnalyzeAddon($Path);
         if ($FileAddonData) {
            $AddonData = array_merge($AddonData, ArrayTranslate($FileAddonData, array('AddonKey' => 'File_AddonKey', 'Name' => 'File_Name', 'File_Type', 'Description' => 'File_Description', 'Requirements' => 'File_Requirements', 'Checked' => 'File_Checked')));
            $AddonData['File_Type'] = GetValueR($FileAddonData['AddonTypeID'].'.Label', $AddonTypes, 'Unknown');
         }
      } catch (Exception $Ex) {
         $AddonData['File_Error'] = $Ex->getMessage();
      }
      $this->SetData('Addon', $AddonData);

      // Go through the versions and make sure we get the versions to check out.
      $Versions = array();
      foreach ($Addon['Versions'] as $Version) {
         $Version = $Version;
         $Path = PATH_LOCAL_UPLOADS."/{$Version['File']}";

         try {
            $VersionData = ArrayTranslate((array)$Version, array('AddonVersionID', 'Version', 'AddonKey', 'Name', 'MD5', 'FileSize', 'Checked'));
            
            $FileVersionData = UpdateModel::AnalyzeAddon($Path);
            $FileVersionData = ArrayTranslate($FileVersionData, array('Version' => 'File_Version', 'AddonKey' => 'File_AddonKey', 'Name' => 'File_Name', 'MD5' => 'File_MD5', 'FileSize' => 'File_FileSize', 'Checked' => 'File_Checked'));
         } catch (Exception $Ex) {
            $FileVersionData = array('File_Error' => $Ex->getMessage());
         }
         $Versions[] = array_merge($VersionData, $FileVersionData);
      }
      $this->SetData('Versions', $Versions);

      $this->AddModule('AddonHelpModule');
      $this->Render();
   }

   public function DeleteVersion($VersionID) {
      $this->Permission('Addons.Addon.Manage');
      $Version = $this->AddonModel->GetVersion($VersionID);
      $this->Data = $Version;

      if ($this->Form->AuthenticatedPostBack() && $this->Form->GetFormValue('Yes')) {
         $this->AddonModel->DeleteVersion($VersionID);

         // Update the current version of the addon.
         $AddonID = GetValue('AddonID', $Version);
         $this->AddonModel->UpdateCurrentVersion($AddonID);
         $this->RedirectUrl = Url('/addon/check/'.$AddonID);
      }
      $this->Render();
   }

   public function Edit($AddonID = '') {
		$this->Permission('Addons.Addon.Add');

		$this->AddJsFile('/js/library/jquery.autogrow.js');
		$this->AddJsFile('forms.js');

		$Session = Gdn::Session();
      $Addon = $this->AddonModel->GetID($AddonID);
      if (!$Addon)
         throw NotFoundException('Addon');

      if ($Addon['InsertUserID'] != $Session->UserID)
         $this->Permission('Addons.Addon.Manage');

      $this->Form->SetModel($this->AddonModel);
      $this->Form->AddHidden('AddonID', $AddonID);
      $AddonTypeModel = new Gdn_Model('AddonType');
      $this->TypeData = $AddonTypeModel->GetWhere(array('Visible' => '1'));

      if ($this->Form->AuthenticatedPostBack() === FALSE) {
         $this->Form->SetData($Addon);
      } else {
         if ($this->Form->Save() !== FALSE) {
            $Addon = $this->AddonModel->GetID($AddonID);
            $this->StatusMessage = T("Your changes have been saved successfully.");
            $this->RedirectUrl = Url('/addon/'.AddonModel::Slug($Addon));
         }
      }

      $this->Render();
   }
   
   public function EditV1($AddonID = '') {
		// $this->Permission('Addons.Addon.Manage');
		
		$this->AddJsFile('/js/library/jquery.autogrow.js');
		$this->AddJsFile('forms.js');
      
		$Session = Gdn::Session();
      $Addon = $this->AddonModel->GetID($AddonID);
      if (!$Addon)
         Redirect('dashboard/home/filenotfound');
         
      if ($Addon['InsertUserID'] != $Session->UserID)
         $this->Permission('Addons.Addon.Manage');
         
      $this->Form->SetModel($this->AddonModel);
      $this->Form->AddHidden('AddonID', $AddonID);
      $AddonTypeModel = new Gdn_Model('AddonType');
      $this->TypeData = $AddonTypeModel->GetWhere(array('Visible' => '1'));
      
      if ($this->Form->AuthenticatedPostBack() === FALSE) {
         $this->Form->SetData($Addon);
      } else {
         if ($this->Form->Save(TRUE) !== FALSE) {
            $Addon = $this->AddonModel->GetID($AddonID);
            $this->StatusMessage = T("Your changes have been saved successfully.");
            $this->RedirectUrl = Url('/addon/'.AddonModel::Slug($Addon));
         }
      }
      
      $this->Render();
   }

   public function NewVersion($AddonID = '') {
      $this->_NewVersion($AddonID);
   }
   
   protected function _NewVersion($AddonID = '', $V1 = FALSE) {
		$Session = Gdn::Session();
      $Addon = $this->AddonModel->GetID($AddonID);
      if (!$Addon)
         Redirect('dashboard/home/filenotfound');
         
      if ($Addon['InsertUserID'] != $Session->UserID)
         $this->Permission('Addons.Addon.Manage');

      $this->Form->SetModel($this->AddonModel);
      $this->Form->AddHidden('AddonID', $AddonID);
      
      if ($this->Form->AuthenticatedPostBack()) {
         $Upload = new Gdn_Upload();
         $Upload->AllowFileExtension(NULL);
         $Upload->AllowFileExtension('zip');
         try {
            // Validate the upload
            $TmpFile = $Upload->ValidateUpload('File');
            $Extension = pathinfo($Upload->GetUploadedFileName(), PATHINFO_EXTENSION);
            
            // Generate the target name
            $TargetFile = $Upload->GenerateTargetName('addons', $Extension);
            $FileBaseName = pathinfo($TargetFile, PATHINFO_BASENAME);
            
            // Save the uploaded file
            $Upload->SaveAs(
               $TmpFile,
               $TargetFile
            );
            $Path = $Upload->CopyLocal($TargetFile);
            
            $this->Form->SetFormValue('Path', $Path);
//				$this->Form->SetFormValue('TestedWith', 'Blank');
         } catch (Exception $ex) {
            $this->Form->AddError($ex->getMessage());
         }
         
         // If there were no errors, save the addonversion
         if ($this->Form->ErrorCount() == 0) {
            $NewVersionID = $this->Form->Save($V1);
            if ($NewVersionID) {
               $this->StatusMessage = T("New version saved successfully.");
               $this->RedirectUrl = Url('/addon/'.AddonModel::Slug($Addon, FALSE));
            } else {
               if (file_exists($Path))
                  unlink($Path);
            }
         }
      }
      $this->Render();      
   }

   public function NewVersionV1($AddonID = '') {
      $this->_NewVersion($AddonID, TRUE);
   }

   public function NotFound() {
      $this->Render();
   }
   
   public function Approve($AddonID = '') {
      $this->Permission('Addons.Addon.Manage');
      $Session = Gdn::Session();
      $Addon = $this->Addon = $this->AddonModel->GetID($AddonID);
      $VersionModel = new Gdn_Model('AddonVersion');
      if (!$Addon['DateReviewed']) {
         $VersionModel->Save(array('AddonVersionID' => $Addon['AddonVersionID'], 'DateReviewed' => Gdn_Format::ToDateTime()));
      } else {
         $VersionModel->Update(array('DateReviewed' => null), array('AddonVersionID' => $Addon['AddonVersionID']));
      }
      
      Redirect('/addon/'.AddonModel::Slug($Addon));
  }

   public function Delete($AddonID = '') {
      $this->Permission('Addons.Addon.Manage');
      $Session = Gdn::Session();
      if (!$Session->IsValid())
         $this->Form->AddError('You must be authenticated in order to use this form.');

      $Addon = $this->AddonModel->GetID($AddonID);
      if (!$Addon)
         Redirect('dashboard/home/filenotfound');

      if ($Session->UserID != $Addon['InsertUserID'])
			$this->Permission('Addons.Addon.Manage');

      $Session = Gdn::Session();
      if (is_numeric($AddonID)) 
         $this->AddonModel->Delete($AddonID);

      if ($this->_DeliveryType === DELIVERY_TYPE_ALL)
         Redirect(GetIncomingValue('Target', Gdn_Url::WebRoot()));

      $this->View = 'index';
      $this->Render();
   }

   /**
    * Add a comment to an addon
    */
   public function AddComment($AddonID = '') {
      $Render = TRUE;
      $this->Form->SetModel($this->AddonCommentModel);
      $AddonID = $this->Form->GetFormValue('AddonID', $AddonID);

      if (is_numeric($AddonID) && $AddonID > 0)
         $this->Form->AddHidden('AddonID', $AddonID);
      
      if ($this->Form->AuthenticatedPostBack()) {
         $NewCommentID = $this->Form->Save();
         // Comment not saving for some reason - no errors reported
         if ($NewCommentID > 0) {
            // Update the Comment count
            $this->AddonModel->SetProperty($AddonID, 'CountComments', $this->AddonCommentModel->GetCount(array('AddonID' => $AddonID)));
            if ($this->DeliveryType() == DELIVERY_TYPE_ALL)
               Redirect('addon/'.$AddonID.'/#Comment_'.$NewCommentID);
               
            $this->SetJson('CommentID', $NewCommentID);
            // If this was not a full-page delivery type, return the partial response
            // Load all new messages that the user hasn't seen yet (including theirs)
            $LastCommentID = $this->Form->GetFormValue('LastCommentID');
            if (!is_numeric($LastCommentID))
               $LastCommentID = $NewCommentID - 1;
            
            $Session = Gdn::Session();
            $this->Addon = $this->AddonModel->GetID($AddonID);   
            $this->CommentData = $this->AddonCommentModel->GetNew($AddonID, $LastCommentID);
            $this->View = 'comments';
         } else {
            // Handle ajax based errors...
            if ($this->DeliveryType() != DELIVERY_TYPE_ALL) {
               $this->StatusMessage = $this->Form->Errors();
            } else {
               $Render = FALSE;
               $this->Index($AddonID);
            }
         }
      }

      if ($Render)
         $this->Render();      
   }
   
   public function DeleteComment($CommentID = '') {
      $this->Permission('Addons.Comments.Manage');
      $Session = Gdn::Session();
      if (is_numeric($CommentID))
         $this->AddonCommentModel->Delete($CommentID);

      if ($this->_DeliveryType === DELIVERY_TYPE_ALL) {
         Redirect(Url(GetIncomingValue('Return', ''), TRUE));
      }
         
      $this->View = 'notfound';
      $this->Render();
   }
   
	public function Browse($FilterToType = '', $Sort = '', $VanillaVersion = '', $Page = '') {
      $Checked = GetIncomingValue('checked', FALSE);

		// Implement user prefs
		$Session = Gdn::Session();
		if ($Session->IsValid()) {
			if ($FilterToType != '') {
				$Session->SetPreference('Addons.FilterType', $FilterToType);
			}
			if ($VanillaVersion != '')
				$Session->SetPreference('Addons.FilterVanilla', $VanillaVersion);
			if ($Sort != '')
				$Session->SetPreference('Addons.Sort', $Sort);
         if ($Checked !== FALSE)
            $Session->SetPreference('Addons.FilterChecked', $Checked);
			
			$FilterToType = $Session->GetPreference('Addons.FilterType', 'all');
			$VanillaVersion = $Session->GetPreference('Addons.FilterVanilla', '2');
			$Sort = $Session->GetPreference('Addons.Sort', 'recent');
         $Checked = $Session->GetPreference('Addons.FilterChecked');
		}
		
		if (!array_key_exists($FilterToType, AddonModel::$TypesPlural))
			$FilterToType = 'all';
		
		if ($Sort != 'popular')
			$Sort = 'recent';
		
		if (!in_array($VanillaVersion, array('1', '2')))
			$VanillaVersion = '2';
		
		$this->Version = $VanillaVersion;
			
		$this->Sort = $Sort;

      $this->FilterChecked = $Checked;

		$this->AddJsFile('/js/library/jquery.gardenmorepager.js');
		$this->AddJsFile('browse.js');

      list($Offset, $Limit) = OffsetLimit($Page, Gdn::Config('Garden.Search.PerPage', 20));
		
      $this->Filter = $FilterToType;
		$Search = GetIncomingValue('Form/Keywords', '');
		$this->_BuildBrowseWheres($Search);
				
		$SortField = $Sort == 'recent' ? 'DateUpdated' : 'CountDownloads';
		$ResultSet = $this->AddonModel->GetWhere(FALSE, $SortField, 'desc', $Limit, $Offset);
		$this->SetData('Addons', $ResultSet);
		$this->_BuildBrowseWheres($Search);
		$NumResults = $this->AddonModel->GetCount(FALSE);
      $this->SetData('TotalAddons', $NumResults);
		
		// Build a pager
		$PagerFactory = new Gdn_PagerFactory();
		$Pager = $PagerFactory->GetPager('Pager', $this);
		$Pager->MoreCode = '›';
		$Pager->LessCode = '‹';
		$Pager->ClientID = 'Pager';
		$Pager->Configure(
			$Offset,
			$Limit,
			$NumResults,
			'addon/browse/'.$FilterToType.'/'.$Sort.'/'.$this->Version.'/%1$s/?Form/Keywords='.Gdn_Format::Url($Search)
		);
		$this->SetData('_Pager', $Pager);
      
      if ($this->_DeliveryType != DELIVERY_TYPE_ALL)
         $this->SetJson('MoreRow', $Pager->ToString('more'));
      
		$this->AddModule('AddonHelpModule');
		
		$this->Render();
	}
	
	private function _BuildBrowseWheres($Search = '') {
      if ($Search != '') {
         $this->AddonModel
            ->SQL
            ->BeginWhereGroup()
            ->Like('a.Name', $Search)
            ->OrLike('a.Description', $Search)
            ->EndWhereGroup();
		}
		
		if ($this->Version != 0)
			$this->AddonModel
				->SQL
				->Where('a.Vanilla2', $this->Version == '1' ? '0' : '1');

      $Ch = array('unchecked' => 0, 'checked' => 1);
      if (isset($Ch[$this->FilterChecked])) {
         $this->AddonModel->SQL->Where('a.Checked', $Ch[$this->FilterChecked]);
      }

      if ($Types = $this->Request->Get('Types')) {
         $Types = explode(',', $Types);
         foreach ($Types as $Index => $Type) {
            if (isset(AddonModel::$Types[trim($Type)]))
               $Types[$Index] = AddonModel::$Types[trim($Type)];
            else
               unset($Types[$Index]);
         }
         $this->AddonModel->SQL->WhereIn('a.AddonTypeID', $Types);
      }

      $AddonTypeID = GetValue($this->Filter, AddonModel::$TypesPlural);
      if ($AddonTypeID)
			$this->AddonModel
				->SQL
				->Where('a.AddonTypeID', $AddonTypeID);
	}
   
   public function AddPicture($AddonID = '') {
      $Session = Gdn::Session();
      if (!$Session->IsValid())
         $this->Form->AddError('You must be authenticated in order to use this form.');

      $Addon = $this->AddonModel->GetID($AddonID);
      if (!$Addon)
         throw NotFoundException('Addon');

      if ($Session->UserID != $Addon['InsertUserID'])
			$this->Permission('Addons.Addon.Manage');
         
      $AddonPictureModel = new Gdn_Model('AddonPicture');
      $this->Form->SetModel($AddonPictureModel);
      $this->Form->AddHidden('AddonID', $AddonID);
      if ($this->Form->AuthenticatedPostBack() === TRUE) {
         $UploadImage = new Gdn_UploadImage();
         try {
            // Validate the upload
            $TmpImage = $UploadImage->ValidateUpload('Picture');
            
            // Generate the target image name
            $TargetImage = $UploadImage->GenerateTargetName(PATH_LOCAL_UPLOADS, '');
            $ImageBaseName = 'addons/screens/'.pathinfo($TargetImage, PATHINFO_BASENAME);
            
            // Save the uploaded image in large size
            $UploadImage->SaveImageAs(
               $TmpImage,
               ChangeBaseName($ImageBaseName, 'ao%s'),
               700,
               1000
            );

            // Save the uploaded image in thumbnail size
            $ThumbSize = 150;
            $UploadImage->SaveImageAs(
               $TmpImage,
               ChangeBasename($ImageBaseName, 'at%s'),
               $ThumbSize,
               $ThumbSize
            );
            
         } catch (Exception $ex) {
            $this->Form->AddError($ex->getMessage());
         }
         // If there were no errors, insert the picture
         if ($this->Form->ErrorCount() == 0) {
            $AddonPictureModel = new Gdn_Model('AddonPicture');
            $AddonPictureID = $AddonPictureModel->Insert(array('AddonID' => $AddonID, 'File' => $ImageBaseName));
         }

         // If there were no problems, redirect back to the addon
         if ($this->Form->ErrorCount() == 0)
            $this->RedirectUrl = Url('/addon/'.AddonModel::Slug($Addon));
      }
      $this->Render();
   }
   
   public function DeletePicture($AddonPictureID = '') {
      $this->Permission('Addons.Addon.Manage');

      if ($this->Form->AuthenticatedPostBack() && $this->Form->GetFormValue('Yes')) {
         $AddonPictureModel = new Gdn_Model('AddonPicture');
         $Picture = $AddonPictureModel->GetWhere(array('AddonPictureID' => $AddonPictureID))->FirstRow();
         $Upload = new Gdn_Upload();
         
         if ($Picture) {
            $Upload->Delete(ChangeBasename($Picture->File, 'ao%s'));
            $Upload->Delete(ChangeBasename($Picture->File, 'at%s'));
            $AddonPictureModel->Delete(array('AddonPictureID' => $AddonPictureID));
         }
         $this->RedirectUrl = Url('/addon/'.$Picture->AddonID);
      }
      $this->Render('deleteversion');
   }

   public function GetList($IDs) {
      $IDs = explode(',', $IDs);
      array_map('trim', $IDs);

      $Addons = $this->AddonModel->GetIDs($IDs);
      $this->SetData('Addons', $Addons);

      $this->Render('browse');
   }
   
   public function Icon($AddonID = '') {
      $Session = Gdn::Session();
      if (!$Session->IsValid())
         $this->Form->AddError('You must be authenticated in order to use this form.');

      $Addon = $this->AddonModel->GetID($AddonID);
      if (!$Addon)
         throw NotFoundException('Addon');

      if ($Session->UserID != $Addon['InsertUserID'])
			$this->Permission('Addons.Addon.Manage');

      $this->Form->SetModel($this->AddonModel);
      $this->Form->AddHidden('AddonID', $AddonID);
      if ($this->Form->AuthenticatedPostBack() === TRUE) {
         $UploadImage = new Gdn_UploadImage();
         try {
            // Validate the upload
            $TmpImage = $UploadImage->ValidateUpload('Icon');
            
            // Generate the target image name
            $TargetImage = $UploadImage->GenerateTargetName('addons/icons', '');
            $ImageBaseName = pathinfo($TargetImage, PATHINFO_BASENAME);
            
            // Save the uploaded icon
            $UploadImage->SaveImageAs(
               $TmpImage,
               $TargetImage,
               128,
               128,
               FALSE, FALSE
            );

         } catch (Exception $ex) {
            $this->Form->AddError($ex->getMessage());
         }
         // If there were no errors, remove the old picture and insert the picture
         if ($this->Form->ErrorCount() == 0) {
//            $Addon = $this->AddonModel->GetID($AddonID);
            if ($Addon['Icon']) {
               $UploadImage->Delete($Addon['Icon']);
            }
               
            $this->AddonModel->Save(array('AddonID' => $AddonID, 'Icon' => $TargetImage));
         }

         // If there were no problems, redirect back to the addon
         if ($this->Form->ErrorCount() == 0)
            $this->RedirectUrl = Url('/addon/'.AddonModel::Slug($Addon));
      }
      $this->Render();
   }
}