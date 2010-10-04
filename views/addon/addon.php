<?php if (!defined('APPLICATION')) exit();
$Session = Gdn::Session();
$VanillaVersion = $this->Data('Vanilla2') == '1' ? '2' : '1';

if ($this->DeliveryType() == DELIVERY_TYPE_ALL) {
	// echo $this->FetchView('head');
	?>
	<h1>
		<div>
			<?php echo T('Found in: ');
			echo Anchor('Addons', '/addon/browse/');
			?>
			<span>&rarr;</span> <?php
            $TypesPlural = array_flip($this->Data('_TypesPlural'));
            $TypePlural = GetValue($this->Data('AddonTypeID'), $TypesPlural, 'all');
            echo Anchor(T($TypePlural), '/addon/browse/'.strtolower($TypePlural));
         ?>
		</div>
		<?php echo $this->Data('Name'); ?>
		<?php echo $this->Data('Version'); ?>
	</h1>
	<?php
   $AddonID = $this->Data('AddonID');
	if ($Session->UserID == $this->Data('InsertUserID') || $Session->CheckPermission('Addons.Addon.Manage')) {
      $Ver = ($this->Data('Checked') ? '' : 'v1');

		echo '<div class="AddonOptions">';
		echo Anchor('Edit Details', "/addon/edit{$Ver}/$AddonID", 'Popup');
		echo '|'.Anchor('Upload New Version', "/addon/newversion{$Ver}/$AddonID", 'Popup');
		echo '|'.Anchor('Upload Screen', '/addon/addpicture/'.$AddonID, 'Popup');
		echo '|'.Anchor('Upload Icon', '/addon/icon/'.$AddonID, 'Popup');
      if ($Session->CheckPermission('Addons.Addon.Manage'))
         echo '|'.Anchor('Check', '/addon/check/'.$AddonID);
		if ($Session->CheckPermission('Addons.Addon.Approve'))
			echo '|'.Anchor($this->Data('DateReviewed') == '' ? 'Approve Version' : 'Unapprove Version', '/addon/approve/'.$AddonID, 'ApproveAddon');
		
		echo '|'.Anchor('Delete Addon', '/addon/delete/'.$AddonID.'?Target=/addon', 'DeleteAddon');
		echo '</div>';
	}
	if ($this->Data('DateReviewed') == '')
		echo '<div class="Warning"><strong>Warning!</strong> This community-contributed addon has not been tested or code-reviewed. Use at your own risk.</div>';
	else
		echo '<div class="Approved"><strong>Approved!</strong> This addon has been reviewed and approved by Vanilla Forums staff.</div>';

	?>
	<div class="Legal">
		<div class="DownloadPanel">
			<div class="Box DownloadBox">
				<p><?php echo Anchor('Download Now', '/get/'.($this->Data('Slug') ? urlencode($this->Data('Slug')) : $AddonID), 'BigButton'); ?></p>
				<dl>
					<dt>Author</dt>
					<dd><?php echo Anchor($this->Data('InsertName'), '/profile/'.urlencode($this->Data('InsertName'))); ?></dd>
					<dt>Version</dt>
					<dd><?php echo $this->Data('Version').'&nbsp;'; ?></dd>
					<dt>Released</dt>
					<dd><?php echo Gdn_Format::Date($this->Data('DateUploaded')); ?></dd>
					<dt>Downloads</dt>
					<dd><?php echo number_format($this->Data('CountDownloads')); ?></dd>
				</dl>
			</div>
			<div class="Box RequirementBox">
            <h3><?php echo T('Requirements') ?></h3>
				<dl>
					<dt>Vanilla</dt>
					<dd><span class="Vanilla<?php echo $VanillaVersion; ?>">Vanilla <?php echo $VanillaVersion; ?></span></dd>
				</dl>
				<?php
            if (!$this->Data('Checked')) {
               $OtherRequirements = Gdn_Format::Display($this->Data('Requirements'));
               if ($OtherRequirements) {
                  ?>
                  <p>Other Requirements:</p>
                  <?php
                  echo $OtherRequirements;
               }
            } else {
               $OtherRequirements = Gdn_Format::Html($this->Data('Requirements'));
               if ($OtherRequirements) {
                  echo $OtherRequirements;
               }
            }
				?>
			</div>
		</div>
	<?php
	if ($this->Data('Icon') != '')
		echo '<img class="Icon" src="'.Url('uploads/ai'.$this->Data('Icon')).'" />';
		
	echo Gdn_Format::Html($this->Data('Description'));
   if ($this->Data('Description2')) {
      echo '<br /><br />', Gdn_Format::Html($this->Data('Description2'));
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
		echo Anchor('Ask a Question', 'post/discussion?AddonID='.$AddonID, 'TabLink');
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