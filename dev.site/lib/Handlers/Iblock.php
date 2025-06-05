<?php

namespace Only\Site\Handlers;

class Iblock
{
    public function addLog(&$arFields)
    {
        static $isRunning = false;

        if ($isRunning) {
            return;
        }

        $isRunning = true;

        $userID = \Bitrix\Main\Engine\CurrentUser::get()->getId();

        $iblock = \CIBlock::GetList([], ['ID' => $arFields['IBLOCK_ID']])->Fetch();

        $logIblock = \CIBlock::GetList([], ['CODE' => 'LOG'])->Fetch();

        if (!$logIblock) {
            echo "Инфоблок LOG не найден";
            return;
        }

        $logIdIblock = $logIblock['ID'];

        if ($arFields['IBLOCK_ID'] == $logIdIblock) {
            return;
        }

        $logSectionId = \CIBlockSection::GetList([], ['CODE' => $iblock['CODE']])->Fetch()['ID'];       
        
        if (!$logSectionId) {
            $bs = new \CIBlockSection;
            $logSectionArFields = Array(
                "ACTIVE" => 'Y',
                "IBLOCK_SECTION_ID" => false,
                "CODE" => $iblock['CODE'],
                "IBLOCK_ID" => $logIdIblock,
                "NAME" => $iblock['NAME'],
                "SORT" => 500,
                "DESCRIPTION_TYPE" => 'text'
            );

            $logSectionId = $bs->Add($logSectionArFields);
            if (!$logSectionId) {
                echo "Error: " . $bs->LAST_ERROR;
                return;
            }
        }


        $el = new \CIBlockElement;

        $previewText = $this->getLogDescription($arFields['ID'], $iblock["NAME"]);

        $logElActiveFrom = null;

        if (!$arFields['ACTIVE_FROM'] || $arFields['ACTIVE_FROM'] == '') {
            $logElActiveFrom = $arFields['DATE_CREATE'];
        } else {
            $logElActiveFrom = $arFields['TIMESTAMP_X'];
        }

        $logElActiveFromDate = (string) \Bitrix\Main\Type\DateTime::createFromPhp(
            \DateTime::createFromFormat('d.m.Y H:i:s', $logElActiveFrom) ?: new \DateTime()
        );

        $arLoadProductArray = Array(
            "MODIFIED_BY"    => $userID,
            "IBLOCK_SECTION_ID" => $logSectionId,
            "IBLOCK_ID"      => $logIdIblock,
            "NAME"           => $arFields['ID'],
            "ACTIVE"         => "Y",
            "ACTIVE_FROM"    => $logElActiveFromDate,
            "PREVIEW_TEXT"   => $previewText
        );
        

        if ($PRODUCT_ID = $el->Add($arLoadProductArray))
            echo "New ID: ".$PRODUCT_ID;
        else
            echo "Error: ".$el->LAST_ERROR;

        $isRunning = false;
    }

    private function getLogDescription($elementId, $iblockName, $sectionPath = '') {

        $element = \CIBlockElement::GetList([], ['ID' => $elementId])->Fetch();
        $section = \CIBlockElement::GetElementGroups($elementId, true)->Fetch();
        $fullSectionPath = '';
        
        if ($section) {
            $parentSectionPath = '';
            if ($section['IBLOCK_SECTION_ID']) {
                $parentSectionPath = $this->getSectionPath($section['IBLOCK_SECTION_ID']);
            }

            $fullSectionPath = $parentSectionPath 
                ? $parentSectionPath . ' -> ' . $section['NAME'] 
                : $section['NAME'];
        }
    
        return $iblockName . ($fullSectionPath ? ' -> ' . $fullSectionPath : '') . ' -> ' . $element['NAME'];
    }
    
    private function getSectionPath($sectionId) {
        
        $section = \CIBlockSection::GetList([], ['ID' => $sectionId])->Fetch();
        
        if (!$section) return '';
        
        if ($section['IBLOCK_SECTION_ID']) {
            $parentPath = $this->getSectionPath($section['IBLOCK_SECTION_ID']);
            return $parentPath ? $parentPath . ' -> ' . $section['NAME'] : $section['NAME'];
        }
        
        return $section['NAME'];
    }

    function OnBeforeIBlockElementAddHandler(&$arFields)
    {
        $iQuality = 95;
        $iWidth = 1000;
        $iHeight = 1000;
        /*
         * Получаем пользовательские свойства
         */
        $dbIblockProps = \Bitrix\Iblock\PropertyTable::getList(array(
            'select' => array('*'),
            'filter' => array('IBLOCK_ID' => $arFields['IBLOCK_ID'])
        ));
        /*
         * Выбираем только свойства типа ФАЙЛ (F)
         */
        $arUserFields = [];
        while ($arIblockProps = $dbIblockProps->Fetch()) {
            if ($arIblockProps['PROPERTY_TYPE'] == 'F') {
                $arUserFields[] = $arIblockProps['ID'];
            }
        }
        /*
         * Перебираем и масштабируем изображения
         */
        foreach ($arUserFields as $iFieldId) {
            foreach ($arFields['PROPERTY_VALUES'][$iFieldId] as &$file) {
                if (!empty($file['VALUE']['tmp_name'])) {
                    $sTempName = $file['VALUE']['tmp_name'] . '_temp';
                    $res = \CAllFile::ResizeImageFile(
                        $file['VALUE']['tmp_name'],
                        $sTempName,
                        array("width" => $iWidth, "height" => $iHeight),
                        BX_RESIZE_IMAGE_PROPORTIONAL_ALT,
                        false,
                        $iQuality);
                    if ($res) {
                        rename($sTempName, $file['VALUE']['tmp_name']);
                    }
                }
            }
        }

        if ($arFields['CODE'] == 'brochures') {
            $RU_IBLOCK_ID = \Only\Site\Helpers\IBlock::getIblockID('DOCUMENTS', 'CONTENT_RU');
            $EN_IBLOCK_ID = \Only\Site\Helpers\IBlock::getIblockID('DOCUMENTS', 'CONTENT_EN');
            if ($arFields['IBLOCK_ID'] == $RU_IBLOCK_ID || $arFields['IBLOCK_ID'] == $EN_IBLOCK_ID) {
                \CModule::IncludeModule('iblock');
                $arFiles = [];
                foreach ($arFields['PROPERTY_VALUES'] as $id => &$arValues) {
                    $arProp = \CIBlockProperty::GetByID($id, $arFields['IBLOCK_ID'])->Fetch();
                    if ($arProp['PROPERTY_TYPE'] == 'F' && $arProp['CODE'] == 'FILE') {
                        $key_index = 0;
                        while (isset($arValues['n' . $key_index])) {
                            $arFiles[] = $arValues['n' . $key_index++];
                        }
                    } elseif ($arProp['PROPERTY_TYPE'] == 'L' && $arProp['CODE'] == 'OTHER_LANG' && $arValues[0]['VALUE']) {
                        $arValues[0]['VALUE'] = null;
                        if (!empty($arFiles)) {
                            $OTHER_IBLOCK_ID = $RU_IBLOCK_ID == $arFields['IBLOCK_ID'] ? $EN_IBLOCK_ID : $RU_IBLOCK_ID;
                            $arOtherElement = \CIBlockElement::GetList([],
                                [
                                    'IBLOCK_ID' => $OTHER_IBLOCK_ID,
                                    'CODE' => $arFields['CODE']
                                ], false, false, ['ID'])
                                ->Fetch();
                            if ($arOtherElement) {
                                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                                \CIBlockElement::SetPropertyValues($arOtherElement['ID'], $OTHER_IBLOCK_ID, $arFiles, 'FILE');
                            }
                        }
                    } elseif ($arProp['PROPERTY_TYPE'] == 'E') {
                        $elementIds = [];
                        foreach ($arValues as &$arValue) {
                            if ($arValue['VALUE']) {
                                $elementIds[] = $arValue['VALUE'];
                                $arValue['VALUE'] = null;
                            }
                        }
                        if (!empty($arFiles && !empty($elementIds))) {
                            $rsElement = \CIBlockElement::GetList([],
                                [
                                    'IBLOCK_ID' => \Only\Site\Helpers\IBlock::getIblockID('PRODUCTS', 'CATALOG_' . $RU_IBLOCK_ID == $arFields['IBLOCK_ID'] ? '_RU' : '_EN'),
                                    'ID' => $elementIds
                                ], false, false, ['ID', 'IBLOCK_ID', 'NAME']);
                            while ($arElement = $rsElement->Fetch()) {
                                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                                \CIBlockElement::SetPropertyValues($arElement['ID'], $arElement['IBLOCK_ID'], $arFiles, 'FILE');
                            }
                        }
                    }
                }
            }
        }
    }

}
