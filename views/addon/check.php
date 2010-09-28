<?php if (!defined('APPLICATION')) exit();

function _CheckTable($Data) {
   echo "<table class='Data' width='100%' style='table-layout: fixed;'>\n";
   echo "<thead><tr><td width='30%'>Field</td><td width='35%'>Current</td><td width='35%'>File</td></tr></thead>";
   $Alt = TRUE;

   foreach ($Data as $Key => $Value) {
      if (StringBeginsWith($Key, 'File_'))
         continue;

      $Value = Gdn_Format::Html($Value);
      $FileValue = Gdn_Format::Html(GetValue('File_'.$Key, $Data));

      if ($Key == 'MD5') {
         $Value = substr($Value, 0, 10);
         $FileValue = substr($FileValue, 0, 10);
      }

      $Class = '';
      if ($Alt)
         $Class = ' class="Alt"';

      echo "<tr{$Class}><th>$Key</th><td>$Value</td><td>$FileValue</td></tr>\n";

      $Alt = !$Alt;
   }

   echo '</table>';
}

echo $this->Form->Open();
echo $this->Form->Errors();

echo '<h2>Addon</h2>';
_CheckTable($this->Data('Addon'));

$AddonID = $this->Data('Addon.AddonID');
foreach ($this->Data('Versions', array()) as $Version) {
   echo '<h2>', GetValue('Version', $Version), '</h2>';
   _CheckTable($Version);
   echo '<p style="text-align: right;">',
      Anchor(T('Delete'), "/addon/deleteversion/{$Version['AddonVersionID']}", 'Button Popup'),
      ' ',
      Anchor(T('Save'), "/addon/check/{$AddonID}?SaveVersionID={$Version['AddonVersionID']}", 'Button'),
      '</p>';
}

echo $this->Form->Close();