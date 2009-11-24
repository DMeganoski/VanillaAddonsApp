<?php if (!defined('APPLICATION')) exit();

class Gdn_TranslationModel extends Gdn_Model {
   public function __construct() {
      parent::__construct('Translation');
   }
   
   public function Get($Where = FALSE, $Limit = FALSE, $Offset = FALSE) {
      $this->SQL
         ->Select('s.TranslationID', '', 'SourceTranslationID')
         ->Select('s.Value', '', 'SourceValue')
         ->Select('t.TranslationID, t.Value')
         ->From('Translation s')
         ->Join('Translation t', 't.SourceTranslationID = s.TranslationID', 'left');

      if ($Where !== FALSE)
         $this->SQL->Where($Where);

      if ($Limit !== FALSE) {
         if ($Offset == FALSE || $Offset < 0)
            $Offset = 0;

         $this->SQL->Limit($Limit, $Offset);
      }

      return $this->SQL->Get();
   }
}