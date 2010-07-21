<?php if (!defined('APPLICATION')) exit(); ?>

<div class="Box">
	<h4>Make Your Own Addons!</h4>
	<ul>
	<?php
		$Session = Gdn::Session();
		echo '<li>'.Anchor('Quick-Start Guide', '/page/AddonQuickStart').'</li>';
		if ($Session->IsValid()) {
			echo '<li>'.Anchor('Upload a New Addon', '/addon/add').'</li>';
		} else {
			echo '<li>'.Anchor('Sign In', '/entry/?Return=/addons', 'SignInPopup').'</li>';
		}
	?>
	</ul>
</div>

<div class="Box What">
	<h4>What is this stuff?</h4>
	<p>Addons are custom features that you can add to your Vanilla forum. Addons are created by our community of developers and people like you!</p>
</div>
	
<div class="Box Approved">
	<h4>Vanilla Approved?</h4>
	<p>We review addons to make sure they are safe and don't cause bugs. An addon is considered to be "Vanilla Approved" once our review process is complete.</p>
</div>

<div class="Box">
	<h4>Don't have Vanilla yet?</h4>
	<?php echo Anchor('Download Vanilla Now', '/download', 'GreenButton BigButton'); ?>
</div>
