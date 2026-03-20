<?php declare(strict_types=1);

/**
 * This file is part of the Firstance BPER project.
 *
 * @copyright Firstance srl.
 */

namespace FirstAdvisory\FAWill\controller\operations;

class ctl_operations
{
    public function getHead(): string
    {
	    return '<meta charset="UTF-8">
		        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
		        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

                <link href="./assets-fa/css/operations/main.css" rel="stylesheet">';
    }

    public function getSubTool(): string
    {
        return '';
    }

    public function getContent(): string
    {
	    return '<div id="app_operations" class="col-lg-12"></div>';
    }

    public function getScript(): string
    {
        return '<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
                <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
                <script src="./assets-fa/js/Operations/app.js"></script>
                <script>
                    const { createApp } = Vue;
                    const app = createApp(OperationsApp);
                    app.mount("#app_operations");
                </script>';
    }
}
