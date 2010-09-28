<?php if (!defined('APPLICATION')) exit();
$Session = Gdn::Session();
$VanillaVersion = $this->Addon->Vanilla2 == '1' ? '2' : '1';

if ($this->DeliveryType() == DELIVERY_TYPE_ALL) {
	// echo $this->FetchView('head');
	?>
	<h1>
		<div>
			<?php echo T('Found in: ');
			echo Anchor('Addons', '/addon/browse/');
			?>
			<span>&rarr;</span> <?php echo Anchor($this->Addon->Type.'s', '/addon/browse/'.strtolower($this->Addon->Type).'s'); ?>
		</div>
		<?php echo $this->Addon->Name; ?>
		<?php echo $this->Addon->Version; ?>
	</h1>
	<?php
	if ($Session->UserID == $this->Addon->InsertUserID || $Session->CheckPermission('Addons.Addon.Manage')) {
		echo '<div class="AddonOptions">';
		echo Anchor('Edit Details', '/addon/edit/'.$this->Addon->AddonID, 'Popup');
		echo '|'.Anchor('Upload New Version', '/addon/newversion/'.$this->Addon->AddonID, 'Popup');
		echo '|'.Anchor('Upload Screen', '/addon/addpicture/'.$this->Addon->AddonID, 'Popup');
		echo '|'.Anchor('Upload Icon', '/addon/icon/'.$this->Addon->AddonID, 'Popup');
      if ($Session->CheckPermission('Addons.Addon.Manage'))
         echo '|'.Anchor('Check', '/addon/check/'.$this->Addon->AddonID);
		if ($Session->CheckPermission('Addons.Addon.Approve'))
			echo '|'.Anchor($this->Addon->DateReviewed == '' ? 'Approve Version' : 'Unapprove Version', '/addon/approve/'.$this->Addon->AddonID, 'ApproveAddon');
		
		echo '|'.Anchor('Delete Addon', '/addon/delete/'.$this->Addon->AddonID.'?Target=/addon', 'DeleteAddon');
		echo '</div>';
	}
	if ($this->Addon->DateReviewed == '')
		echo '<div class="Warning"><strong>Warning!</strong> This community-contributed addon has not been tested or code-reviewed. Use at your own risk.</div>';
	else
		echo '<div class="Approved"><strong>Approved!</strong> This addon has been reviewed and approved by Vanilla Forums staff.</div>';

	?>
	<div class="Legal">
		<div class="DownloadPanel">
			<div class="Box DownloadBox">
				<p><?php echo Anchor('Download Now', '/get/'.$this->Addon->AddonID, 'BigButton'); ?></p>
				<dl>
					<dt>Author</dt>
					<dd><?php echo Anchor($this->Addon->InsertName, '/profile/'.urlencode($this->Addon->InsertName)); ?></dd>
					<dt>Version</dt>
					<dd><?php echo $this->Addon->Version.'&nbsp;'; ?></dd>
					<dt>Released</dt>
					<dd><?php echo Gdn_Format::Date($this->Addon->DateUploaded); ?></dd>
					<dt>Downloads</dt>
					<dd><?php echo number_format($this->Addon->CountDownloads); ?></dd>
				</dl>
			</div>
			<div class="Box RequirementBox">
            <h3><?php echo T('Requirements') ?></h3>
				<dl>
					<dt>Vanilla</dt>
					<dd><span class="Vanilla<?php echo $VanillaVersion; ?>">Vanilla <?php echo $VanillaVersion; ?></span></dd>
				</dl>
				<?php
            if (!$this->Addon->Checked) {
               $OtherRequirements = Gdn_Format::Display($this->Addon->Requirements);
               if ($OtherRequirements) {
                  ?>
                  <p>Other Requirements:</p>
                  <?php
                  echo $OtherRequirements;
               }
            } else {
               $OtherRequirements = Gdn_Format::Html($this->Addon->Requirements);
               if ($OtherRequirements) {
                  echo $OtherRequirements;
               }
            }
				?>
			</div>
		</div>
	<?php
	if ($this->Addon->Icon != '')
		echo '<img class="Icon" src="'.Url('uploads/ai'.$this->Addon->Icon).'" />';
		
	echo Gdn_Format::Html($this->Addon->Description);
   if ($this->Addon->Description2) {
      echo '<br /><br />', Gdn_Format::Html($this->Addon->Description2);
   }
	?>
	</div>
	<?php
	if ($this->PictureData->NumRows() > 0) {
		?>
		<div class="PictureBox">
			<?php
			foreach ($this->PictureData->Result() as $Picture) {
				echo '<a rel="popable[gallery]" href="#Pic_'.$Picture->AddonPictureID.'"><img src="'.Url('uploads/at'.$Picture->File).'" /></a>';
				echo '<div id="Pic_'.$Picture->AddonPictureID.'" style="display: none;"><img src="'.Url('uploads/ao'.$Picture->File).'" /></div>';
			}
			?>
		</div>
		<?php
	}
	?>
	<h2 class="Questions">Questions
	<?php
	if ($Session->IsValid()) {
		echo Anchor('Ask a Question', 'post/discussion?AddonID='.$this->Addon->AddonID, 'TabLink');
	} else {
		echo Anchor('Sign In', '/entry/?Target='.urlencode($this->SelfUrl), 'TabLink'.(C('Garden.SignIn.Popup') ? ' SignInPopup' : ''));
	}
	?></h2>
	<?php if (is_object($this->DiscussionData) && $this->DiscussionData->NumRows() > 0) { ?>
	<ul class="DataList Discussions">
		<?php
		$this->ShowOptions = FALSE;
		include($this->FetchViewLocation('discussions', 'DiscussionsController', 'vanilla'));
		?>
	</ul>
	<?php
	} else {
		?>
		<div class="Empty"><?php echo T('No questions yet.'); ?></div>
		<?php
	}
}