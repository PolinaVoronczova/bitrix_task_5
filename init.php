<?

AddEventHandler("iblock", "OnAfterIBlockElementAdd", function(&$arFields) {
    $handler = new \Only\Site\Handlers\Iblock();
    $handler->addLog($arFields);
});

AddEventHandler("iblock", "OnAfterIBlockElementUpdate", function(&$arFields) {
    $handler = new \Only\Site\Handlers\Iblock();
    $handler->addLog($arFields);
});
