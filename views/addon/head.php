<?php if (!defined('APPLICATION')) exit();
$Session = Gdn::Session();
if (!property_exists($this, 'HideSearch')) {
?>
<div class="SearchForm">
	<?php
	$Url = '/addon/browse/'.$this->Filter.'/';
	$Query = GetIncomingValue('Form/Keywords', '');
	echo $this->Form->Open(array('action' => Url($Url.$this->Sort.'/'.$this->Version)));
	echo $this->Form->Errors();
	echo $this->Form->TextBox('Keywords', array('value' => $Query));
	echo $this->Form->Button('Browse Addons');
	if ($Query != '')
		$Query = '?Form/Keywords='.$Query;
	?>
	<div class="Options">
		<strong>↳</strong>
		<?php
		$Suffix = $this->Sort.'/'.$this->Version.'/'.$Query;
		echo Anchor('Show All Addons', 'addon/browse/all/'.$Suffix, $this->Filter == 'all' ? 'Active' : '');
		?>
		or filter to
		<?php
		echo Anchor('Themes', 'addon/browse/themes/'.$Suffix, $this->Filter == 'themes' ? 'Active' : '');
		echo Anchor('Plugins', 'addon/browse/plugins/'.$Suffix, $this->Filter == 'plugins' ? 'Active' : '');
		echo Anchor('Applications', 'addon/browse/applications/'.$Suffix, $this->Filter == 'applications' ? 'Active' : '');
		?>
	</div>
	<div class="Options">
		<strong>↳</strong> Only show addons for 
		<?php
		if ($this->Version == '0') {
			$CssClass = 'Active';
			$Version = '2';
		} else if ($this->Version == '1') {
			$CssClass = 'Active';
			$Version = '3';
		} else if ($this->Version == '2') {
			$CssClass = '';
			$Version = '0';
		} else if ($this->Version == '3') {
			$CssClass = '';
			$Version = '1';
		}
		echo Anchor('Vanilla 1', $Url.$this->Sort.'/'.$Version.'/'.$Query, $CssClass);
		
		if ($this->Version == '0') {
			$CssClass = 'Active';
			$Version = '1';
		} else if ($this->Version == '1') {
			$CssClass = '';
			$Version = '0';
		} else if ($this->Version == '2') {
			$CssClass = 'Active';
			$Version = '3';
		} else if ($this->Version == '3') {
			$CssClass = '';
			$Version = '2';
		}
		echo Anchor('Vanilla 2', $Url.$this->Sort.'/'.$Version.'/'.$Query, $CssClass);
		?>
	</div>
	<div class="Options OrderOptions">
		<strong>↳</strong> Order by
		<?php
		$Suffix = $this->Version.'/'.$Query;
		echo Anchor('Recent', $Url.'recent/'.$Suffix, $this->Sort == 'recent' ? 'Active' : '');
		echo Anchor('Popular', $Url.'popular/'.$Suffix, $this->Sort == 'popular' ? 'Active' : '');
		?>
	</div>
	<?php
	echo $this->Form->Close();
	?>
</div>
<?php
}