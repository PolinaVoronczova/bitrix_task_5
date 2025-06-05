<?php

namespace Only\Site\Agents;


class Iblock
{
    public static function clearOldLogs()
    {
        $iblockId = CIBlock::GetList([], ['CODE' => 'LOG'])->Fetch()['ID'];
    
        
        $elementsByDateCreate = CIBlockElement::GetList(
            array('DATE_CREATE' => 'DESC'),
            array('IBLOCK_ID' => $iblockId),
            false,
            false,
            array('ID', 'DATE_CREATE')
        );
        
        $elementsToDelete = array();
        $counter = 0;
        
        while ($element = $elementsByDateCreate->Fetch()) {
            $counter++;
            if ($counter > 10) {
                $elementsToDelete[] = $element['ID'];
            }
        }
        
        if (!empty($elementsToDelete)) {
            foreach ($elementsToDelete as $elementId) {
                CIBlockElement::Delete($elementId);
            }
        }
        
        return "clearOldLogs();";
    }

    public static function example()
    {
        global $DB;
        if (\Bitrix\Main\Loader::includeModule('iblock')) {
            $iblockId = \Only\Site\Helpers\IBlock::getIblockID('QUARRIES_SEARCH', 'SYSTEM');
            $format = $DB->DateFormatToPHP(\CLang::GetDateFormat('SHORT'));
            $rsLogs = \CIBlockElement::GetList(['TIMESTAMP_X' => 'ASC'], [
                'IBLOCK_ID' => $iblockId,
                '<TIMESTAMP_X' => date($format, strtotime('-1 months')),
            ], false, false, ['ID', 'IBLOCK_ID']);
            while ($arLog = $rsLogs->Fetch()) {
                \CIBlockElement::Delete($arLog['ID']);
            }
        }
        return '\\' . __CLASS__ . '::' . __FUNCTION__ . '();';
    }
}
