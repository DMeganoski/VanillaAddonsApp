<?php if (!defined('APPLICATION')) exit();

function WriteAddon($Addon, $Alt) {
	$Url = '/addon/'.$Addon->AddonID.'/'.Gdn_Format::Url($Addon->Name);
	?>
	<li class="Item AddonRow<?php echo $Alt; ?>">
		<div class="ItemContent">
			<?php
			echo Anchor($Addon->Name, $Url, 'Title');
			
			if ($Addon->Icon != '')
				echo '<a class="Icon" href="'.Url($Url).'"><img src="'.Url('uploads/ai'.$Addon->Icon).'" /></a>';
	
			echo Anchor(SliceString(Gdn_Format::Text($Addon->Description), 300), $Url);
			?>
			<div class="Meta">
				<span class="<?php echo $Addon->Vanilla2 == '1' ? 'Vanilla2' : 'Vanilla1'; ?>"><?php
					echo $Addon->Vanilla2 == '1' ? 'Vanilla 2' : 'Vanilla 1'; ?></span>
				<?php
				if ($Addon->DateReviewed != '')
					echo '<span class="Approved">Approved</span>';
				?>
				<span class="Type">
					Type
					<span><?php echo $Addon->Type; ?></span>
				</span>
				<span class="Author">
					Author
					<span><?php echo $Addon->InsertName; ?></span>
				</span>
				<span class="Downloads">
					Downloads
					<span><?php echo number_format($Addon->CountDownloads); ?></span>
				</span>
				<span class="Updated">
					Updated
					<span><?php echo Gdn_Format::Date($Addon->DateUpdated); ?></span>
				</span>
			</div>
		</div>
	</li>
<?php
}
