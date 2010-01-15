<?php if (!defined('APPLICATION')) exit();
$Session = Gdn::Session();
$VanillaVersion = $this->Addon->Vanilla2 == '1' ? '2' : '1';

if ($this->DeliveryType() == DELIVERY_TYPE_ALL) {
	echo $this->FetchView('head');
?>
<h2>
	<?php echo Anchor('Addons', '/addon/browse/'); ?>
	<span>&bull;</span> <?php echo Anchor($this->Addon->Type.'s', '/addon/browse/'.strtolower($this->Addon->Type).'s'); ?>
	<span>&bull;</span> <?php echo $this->Addon->Name; ?>
	<?php echo $this->Addon->Version; ?>
</h2>
<?php
if ($Session->UserID == $this->Addon->InsertUserID || $Session->CheckPermission('Addons.Addon.Manage')) {
	echo '<div class="AddonOptions">';
	echo Anchor('Edit Details', '/addon/edit/'.$this->Addon->AddonID, 'Popup');
	echo '|'.Anchor('Upload New Version', '/addon/newversion/'.$this->Addon->AddonID, 'Popup');
	echo '|'.Anchor('Upload Screen', '/addon/addpicture/'.$this->Addon->AddonID, 'Popup');
	echo '|'.Anchor('Upload Icon', '/addon/icon/'.$this->Addon->AddonID, 'Popup');
	if ($Session->CheckPermission('Addons.Addon.Approve'))
		echo '|'.Anchor($this->Addon->DateReviewed == '' ? 'Approve Version' : 'Unapprove Version', '/addon/approve/'.$this->Addon->AddonID, 'ApproveAddon');
	
	echo '|'.Anchor('Delete Addon', '/addon/delete/'.$this->Addon->AddonID.'?Target=/addon', 'DeleteAddon');
	echo '</div>';
}
?>
<div class="Legal">
<?php
if ($this->Addon->Icon != '')
	echo '<img class="Icon" src="'.Url('uploads/ai'.$this->Addon->Icon).'" />';
	
echo Format::Html($this->Addon->Description);
?>
</div>
<?php
if ($this->Addon->DateReviewed == '')
	echo '<div class="Warning"><strong>Warning!</strong> We have not performed any code-review or testing of this addon. Use it at your own risk.</div>';
?>
<div class="Box DownloadBox">
	<dl>
		<dt>Author</dt>
		<dd><?php echo Anchor($this->Addon->InsertName, '/profile/'.urlencode($this->Addon->InsertName)); ?></dd>
		<dt>Version</dt>
		<dd><?php echo $this->Addon->Version.'&nbsp;'; ?></dd>
		<dt>Released</dt>
		<dd><?php echo Format::Date($this->Addon->DateUploaded); ?></dd>
		<dt>Downloads</dt>
		<dd><?php echo $this->Addon->CountDownloads; ?></dd>
	</dl>
	<p class="ChunkyButton"><?php echo Anchor('Download Now', '/get/'.$this->Addon->AddonID); ?></p>
</div>
<div class="Box RequirementBox">
	<h3>Requirements</h3>
	<dl>
		<dt>Requires</dt>
		<dd><span class="Vanilla<?php echo $VanillaVersion; ?>">Vanilla <?php echo $VanillaVersion; ?></span></dd>
	</dl>
	<p>Other Requirements (if any):</p>
	<?php echo Format::Display($this->Addon->Requirements); ?>
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
<h2>Comments</h2>
<ul id="Discussion">
<?php
if ($this->CommentData->NumRows() == 0)
	echo '<li class="Empty">No-one has commented on this addon yet.</li>';
}			
$CurrentOffset = 0;
foreach ($this->CommentData->Result() as $Comment) {
	++$CurrentOffset;
	?>
	<li class="Comment" id="Comment_<?php echo $Comment->AddonCommentID; ?>">
		<a name="Item_<?php echo $CurrentOffset;?>" />
		<ul class="Info<?php echo ($Comment->InsertUserID == $Session->UserID ? ' Mine' : '') ?>">
			<li class="Author">
				<?php 
            $Author = UserBuilder($Comment, 'Insert');
				echo UserPhoto($Author);
				echo UserAnchor($Author);
				?>
			</li>
			<li class="Created">
				<?php
				echo Format::Date($Comment->DateInserted);
				?>
			</li>
		</ul>
		<?php
		if ($Session->CheckPermission('Garden.Activity.Delete'))
			echo Anchor('Delete', '/addon/deletecomment/'.$Comment->AddonCommentID.'/'.$Session->TransientKey().'?Return='.urlencode(Gdn_Url::Request()), 'DeleteComment');

		?>
		<div class="Body"><?php echo Format::To($Comment->Body, $Comment->Format); ?></div>
	</li>
	<?php
}
if ($this->DeliveryType() == DELIVERY_TYPE_ALL) {
	if ($this->CommentData->NumRows() > 0)
		echo '</ul>';
	
	// Write out the comment form
	if ($Session->IsValid()) {
		?>
		<div id="CommentForm">
			<?php
				$this->Form->SetModel($this->AddonCommentModel);
				$this->Form->AddHidden('AddonID', $this->Addon->AddonID);
				$this->Form->Action = Url('/addon/addcomment');
				echo $this->Form->Open();
				echo $this->Form->Errors();
				echo $this->Form->Label('Add Comment', 'Body', array('class' => 'Heading'));
				echo $this->Form->Errors();
				echo $this->Form->TextBox('Body', array('MultiLine' => TRUE));
				echo $this->Form->Button('Post Comment');
				echo $this->Form->Close();
			?>
		</div>
		<?php
	} else {
		?>
		<div class="CommentOption">
			<?php echo Gdn::Translate('Want to take part in this discussion? Click one of these:'); ?>
			<?php echo Anchor('Sign In', '/entry/?Target='.urlencode($this->SelfUrl), 'Button SignInPopup'); ?> 
			<?php echo Anchor('Register For Membership', '/entry/?Target='.urlencode($this->SelfUrl), 'Button'); ?>      
		</div>
		<?php 
	}
	echo $this->Pager->ToString('more');
}