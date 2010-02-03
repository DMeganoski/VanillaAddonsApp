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
   
   public $Uses = array('Form', 'Gdn_AddonModel', 'Gdn_AddonCommentModel');
	public $Filter = 'all';
	public $Sort = 'recent';
	public $Version = '0'; // The version of Vanilla to filter to (0 is no filter)
   
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
   
   public function NotFound() {
      $this->Render();
   }


   /**
    * Home Page
    */
   public function Index($AddonID = '', $AddonName = '', $Offset = '', $Limit = '') {
      if ($AddonID != '') {
         if (!is_numeric($Limit) || $Limit < 0)
            $Limit = 50;
         
         $this->Offset = $Offset;   
         if ($this->Offset < 0)
            $this->Offset = 0;
         
         $this->Addon = $this->AddonModel->GetID($AddonID);
         if (!is_object($this->Addon)) {
            $this->View = 'NotFound';
         } else {
            $this->AddCssFile('popup.css');
            // $this->AddCssFile('prettyPhoto.css');
            $this->AddCssFile('fancyzoom.css');
            $this->AddJsFile('fancyzoom.js');
   			$this->AddJsFile('/js/library/jquery.gardenmorepager.js');
            $this->AddJsFile('addon.js');
            $PictureModel = new Gdn_Model('AddonPicture');
            $this->PictureData = $PictureModel->GetWhere(array('AddonID' => $AddonID));
            
            $this->SetData('CommentData', $this->CommentData = $this->AddonCommentModel->Get($AddonID, $Limit, $this->Offset), TRUE);
   
            $PagerFactory = new PagerFactory();
            $this->Pager = $PagerFactory->GetPager('Pager', $this);
            $this->Pager->ClientID = 'Pager';
            $this->Pager->Configure(
               $this->Offset,
               $Limit,
               $this->Addon->CountComments,
               'addon/'.$AddonID.'/'.Format::Url($this->Addon->Name).'/%1$s/%2$s/'
            );
         
            // Define the form for the comment input
            $this->Form = Gdn::Factory('Form', 'Comment');
            $this->Form->AddHidden('AddonID', $this->Addon->AddonID);
            $this->Form->AddHidden('CommentID', '');
            $this->Form->Action = Url('/addon/comment/');
            
            // Deliver json data if necessary
            if ($this->_DeliveryType != DELIVERY_TYPE_ALL) {
               $this->SetJson('LessRow', $this->Pager->ToString('less'));
               $this->SetJson('MoreRow', $this->Pager->ToString('more'));
            }
            
            $this->View = 'addon';
				$this->Title($this->Addon->Name.' '.$this->Addon->Version.' by '.$this->Addon->InsertName);
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
		$this->Render();
   }
   
   /**
    * Add a new addon
    */
   public function Add() {
		$this->Permission('Addons.Addon.Add');
		$this->AddJsFile('/js/library/jquery.autogrow.js');
		$this->AddJsFile('forms.js');
      
      $this->Form->SetModel($this->AddonModel);
      $AddonTypeModel = new Gdn_Model('AddonType');
      $this->TypeData = $AddonTypeModel->GetWhere(array('Visible' => '1'));
      
      if ($this->Form->AuthenticatedPostBack()) {
         $Upload = new Gdn_Upload();
         try {
            // Validate the upload
            $TmpFile = $Upload->ValidateUpload('File');
            $Extension = pathinfo($Upload->GetUploadedFileName(), PATHINFO_EXTENSION);
            
            // Generate the target file name
            $TargetFile = $Upload->GenerateTargetName(PATH_ROOT . DS . 'uploads', $Extension);
            $FileBaseName = pathinfo($TargetFile, PATHINFO_BASENAME);
            
            // Save the uploaded file
            $Upload->SaveAs(
               $TmpFile,
               PATH_ROOT . DS . 'uploads' . DS . $FileBaseName
            );

         } catch (Exception $ex) {
            $this->Form->AddError($ex->getMessage());
         }
         // If there were no errors, save the addon
         if ($this->Form->ErrorCount() == 0) {
            // Save the addon
            $AddonID = $this->Form->Save($FileBaseName);
            if ($AddonID !== FALSE) {
               // Redirect to the new addon
               $Name = $this->Form->GetFormValue('Name', '');
               Redirect('addon/'.$AddonID.'/'.Format::Url($Name));
            }
         }
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
         Redirect('garden/home/filenotfound');
         
      if ($Addon->InsertUserID != $Session->UserID)
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
            $this->StatusMessage = Translate("Your changes have been saved successfully.");
            $this->RedirectUrl = Url('/addon/'.$AddonID.'/'.Format::Url($Addon->Name));
         }
      }
      
      $this->Render();
   }
   
   public function NewVersion($AddonID = '') {
		$Session = Gdn::Session();
      $Addon = $this->AddonModel->GetID($AddonID);
      if (!$Addon)
         Redirect('garden/home/filenotfound');
         
      if ($Addon->InsertUserID != $Session->UserID)
         $this->Permission('Addons.Addon.Manage');

      $AddonVersionModel = new Gdn_Model('AddonVersion');
      $this->Form->SetModel($AddonVersionModel);
      $this->Form->AddHidden('AddonID', $AddonID);
      
      if ($this->Form->AuthenticatedPostBack()) {
         $Upload = new Gdn_Upload();
         try {
            // Validate the upload
            $TmpFile = $Upload->ValidateUpload('File');
            $Extension = pathinfo($Upload->GetUploadedFileName(), PATHINFO_EXTENSION);
            
            // Generate the target name
            $TargetFile = $Upload->GenerateTargetName(PATH_ROOT . DS . 'uploads', $Extension);
            $FileBaseName = pathinfo($TargetFile, PATHINFO_BASENAME);
            
            // Save the uploaded file
            $Upload->SaveAs(
               $TmpFile,
               PATH_ROOT . DS . 'uploads' . DS . $FileBaseName
            );
            $this->Form->SetFormValue('File', $FileBaseName);
				$this->Form->SetFormValue('TestedWith', 'Blank');

         } catch (Exception $ex) {
            $this->Form->AddError($ex->getMessage());
         }
         
         // If there were no errors, save the addonversion
         if ($this->Form->ErrorCount() == 0) {
            $NewVersionID = $this->Form->Save();
            if ($NewVersionID) {
               $this->AddonModel->Save(array('AddonID' => $AddonID, 'CurrentAddonVersionID' => $NewVersionID));
               $this->StatusMessage = Translate("New version saved successfully.");
               $this->RedirectUrl = Url('/addon/'.$AddonID.'/'.Format::Url($Addon->Name));
            }
         }
      }
      $this->Render();      
   }   
   
   public function Approve($AddonID = '') {
      $this->Permission('Addons.Addon.Manage');
      $Session = Gdn::Session();
      $Addon = $this->Addon = $this->AddonModel->GetID($AddonID);
      $VersionModel = new Gdn_Model('AddonVersion');
      if ($Addon->DateReviewed == '') {
         $VersionModel->Save(array('AddonVersionID' => $Addon->AddonVersionID, 'DateReviewed' => Format::ToDateTime()));
      } else {
         $VersionModel->Update(array('DateReviewed' => null), array('AddonVersionID' => $Addon->AddonVersionID));
      }
      
      Redirect('addon/'.$AddonID.'/'.Format::Url($Addon->Name));
  }

   public function Delete($AddonID = '') {
      $Session = Gdn::Session();
      if (!$Session->IsValid())
         $this->Form->AddError('You must be authenticated in order to use this form.');

      $Addon = $this->AddonModel->GetID($AddonID);
      if (!$Addon)
         Redirect('garden/home/filenotfound');

      if ($Session->UserID != $Addon->InsertUserID)
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
   
	public function Browse($FilterToType = 'all', $Sort = 'recent', $VanillaVersion = '0', $Offset = 0, $Limit = NULL) {
		if ($Sort != 'popular')
			$Sort = 'recent';
		
		if (!in_array($VanillaVersion, array('0', '1', '2')))
			$VanillaVersion = '0';
		
		$this->Version = $VanillaVersion;
			
		$this->Sort = $Sort;
		$this->AddJsFile('/js/library/jquery.gardenmorepager.js');
		$this->AddJsFile('browse.js');

		if(!is_numeric($Limit))
			$Limit = Gdn::Config('Garden.Search.PerPage', 20);
		
      $this->Filter = $FilterToType;
		$Search = GetIncomingValue('Form/Keywords', '');
		$this->_BuildBrowseWheres($Search);
				
		$SortField = $Sort == 'recent' ? 'DateUpdated' : 'CountDownloads';
		$ResultSet = $this->AddonModel->GetWhere(FALSE, $SortField, 'desc', $Limit, $Offset);
		$this->SetData('SearchResults', $ResultSet, TRUE);
		$this->_BuildBrowseWheres($Search);
		$NumResults = $this->AddonModel->GetCount(FALSE);
		
		// Build a pager
		$PagerFactory = new PagerFactory();
		$Pager = $PagerFactory->GetPager('Pager', $this);
		$Pager->MoreCode = '›';
		$Pager->LessCode = '‹';
		$Pager->ClientID = 'Pager';
		$Pager->Configure(
			$Offset,
			$Limit,
			$NumResults,
			'addon/browse/'.$FilterToType.'/'.$Sort.'/'.$this->Version.'/%1$s/%2$s/?Form/Keywords='.Format::Url($Search)
		);
		$this->SetData('Pager', $Pager, TRUE);
      
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
      
      if (in_array($this->Filter, array('themes', 'plugins', 'applications')))
			$this->AddonModel
				->SQL
				->Where('t.Label', substr($this->Filter, 0, -1));
	}
   
   public function AddPicture($AddonID = '') {
      $Session = Gdn::Session();
      if (!$Session->IsValid())
         $this->Form->AddError('You must be authenticated in order to use this form.');

      $Addon = $this->AddonModel->GetID($AddonID);
      if (!$Addon)
         Redirect('garden/home/filenotfound');

      if ($Session->UserID != $Addon->InsertUserID)
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
            $TargetImage = $UploadImage->GenerateTargetName(PATH_ROOT . DS . 'uploads');
            $ImageBaseName = pathinfo($TargetImage, PATHINFO_BASENAME);
            
            // Save the uploaded image in large size
            $UploadImage->SaveImageAs(
               $TmpImage,
               PATH_ROOT . DS . 'uploads' . DS . 'ao'.$ImageBaseName,
               1000,
               700
            );

            // Save the uploaded image in thumbnail size
            $ThumbSize = 150;
            $UploadImage->SaveImageAs(
               $TmpImage,
               PATH_ROOT . DS . 'uploads' . DS . 'at'.$ImageBaseName,
               $ThumbSize,
               $ThumbSize,
               TRUE
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
            Redirect('addon/'.$AddonID);
      }
      $this->Render();
   }
   
   public function DeletePicture($AddonPictureID = '') {
      $this->Permission('Addons.Addon.Manage');
      $AddonPictureModel = new Gdn_Model('AddonPicture');
      $Picture = $AddonPictureModel->GetWhere(array('AddonPictureID' => $AddonPictureID));
      if ($Picture) {
         @unlink(PATH_ROOT . DS . 'uploads' . DS . 'ao'.$Picture->Name);
         @unlink(PATH_ROOT . DS . 'uploads' . DS . 'at'.$Picture->Name);
         @unlink(PATH_ROOT . DS . 'uploads' . DS . 'ai'.$Picture->Name);
         $AddonPictureModel->Delete(array('AddonPictureID' => $AddonPictureID));
      }
      if ($this->_DeliveryType === DELIVERY_TYPE_ALL)
         Redirect(GetIncomingValue('Return', Gdn_Url::WebRoot()));

      $this->ControllerName = 'Home';
      $this->View = 'FileNotFound';
      $this->Render();
   }
   
   public function Icon($AddonID = '') {
      $Session = Gdn::Session();
      if (!$Session->IsValid())
         $this->Form->AddError('You must be authenticated in order to use this form.');

      $Addon = $this->AddonModel->GetID($AddonID);
      if (!$Addon)
         Redirect('garden/home/filenotfound');

      if ($Session->UserID != $Addon->InsertUserID)
			$this->Permission('Addons.Addon.Manage');

      $this->Form->SetModel($this->AddonModel);
      $this->Form->AddHidden('AddonID', $AddonID);
      if ($this->Form->AuthenticatedPostBack() === TRUE) {
         $UploadImage = new Gdn_UploadImage();
         try {
            // Validate the upload
            $TmpImage = $UploadImage->ValidateUpload('Icon');
            
            // Generate the target image name
            $TargetImage = $UploadImage->GenerateTargetName(PATH_ROOT . DS . 'uploads');
            $ImageBaseName = pathinfo($TargetImage, PATHINFO_BASENAME);
            
            // Save the uploaded icon
            $UploadImage->SaveImageAs(
               $TmpImage,
               PATH_ROOT . DS . 'uploads' . DS . 'ai'.$ImageBaseName,
               50,
               50
            );

         } catch (Exception $ex) {
            $this->Form->AddError($ex->getMessage());
         }
         // If there were no errors, remove the old picture and insert the picture
         if ($this->Form->ErrorCount() == 0) {
            $Addon = $this->AddonModel->GetID($AddonID);
            if ($Addon->Icon != '')
               @unlink(PATH_ROOT . DS . 'uploads' . DS . 'ai'.$Addon->Icon);
               
            $this->AddonModel->Save(array('AddonID' => $AddonID, 'Icon' => $ImageBaseName));
         }

         // If there were no problems, redirect back to the addon
         if ($this->Form->ErrorCount() == 0)
            Redirect('addon/'.$AddonID);
      }
      $this->Render();
   }
}