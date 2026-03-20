<?php declare(strict_types=1);

/**
 * Autoloader del progetto Settimanale.
 *
 * Carica le variabili d'ambiente da .env, definisce le costanti globali,
 * e include tutte le classi del progetto in ordine di dipendenza.
 *
 * Uso: require_once __DIR__ . '/../lib/autoloader.php';  (da src/)
 *      require_once __DIR__ . '/autoloader.php';          (da lib/)
 */

// Evita inclusioni multiple
if (!defined('AUTOLOADER_LOADED')) {
    define('AUTOLOADER_LOADED', true);

    // Livello 0: infrastruttura DB
    require_once __DIR__ . '/Database.php';
    require_once __DIR__ . '/TraitTryQuery.php';
    require_once __DIR__ . '/env_settings.php';

    // Livello 1: DTO e interfacce
    require_once __DIR__ . '/../src/model/Operations/AjaxRequest.php';
    require_once __DIR__ . '/../src/model/Operations/OperationInterface.php';

    // Livello 2: audit e abstract
    require_once __DIR__ . '/../src/model/Operations/OperationAuditLogger.php';
    require_once __DIR__ . '/../src/model/Operations/AbstractOperation.php';
    require_once __DIR__ . '/../src/model/Operations/AjaxResponseHelper.php';

    // Livello 3: factory
    require_once __DIR__ . '/../src/model/Operations/OperationFactory.php';

    // Livello 4: repository
    require_once __DIR__ . '/../src/model/Operations/NewRetrievalCodeRepository.php';
    require_once __DIR__ . '/../src/model/Operations/ForceAnnulmentRepository.php';
    require_once __DIR__ . '/../src/model/Operations/ResetDocumentStateRepository.php';

    // Livello 5: operation
    require_once __DIR__ . '/../src/model/Operations/NewRetrievalCode.php';
    require_once __DIR__ . '/../src/model/Operations/ForceAnnulment.php';
    require_once __DIR__ . '/../src/model/Operations/ResetDocumentState.php';

    // Livello 6: controller
    require_once __DIR__ . '/../src/controller/Operations/ctl_operations.php';
}