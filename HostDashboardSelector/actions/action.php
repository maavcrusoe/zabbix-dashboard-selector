<?php
namespace Modules\HostDashboardSelector\Actions;

use CController;
use CControllerResponseData;

class action extends CController {

    public function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return true;
    }
    protected function doAction(): void {
        // Cargar el archivo .json
        $configPath = dirname(__DIR__) . '/config.json';
        $config = json_decode(file_get_contents($configPath), true);

        // Acceder a la URL de la API y al token
        $apiUrl = $config['apiUrl'];
        $apiToken = $config['apiToken'];
    
        // Obtener la macro global que contiene los groupids
        $macro = $this->zabbixApiRequest($apiUrl, $apiToken, 'usermacro.get', [
            'globalmacro' => true,
            'output' => ['macro', 'value'],
            'filter' => ['macro' => '{$GROUPIDS}'] // Asegúrate de que el nombre de la macro sea correcto
        ]);
    
        // Convertir el valor de la macro en un array de groupids
        $groupids = [];
        if (!empty($macro)) {
            $groupids = explode(',', $macro[0]['value']);  // Extraer los groupids de la macro
        }
    
        // Verificar si se obtuvieron groupids
        if (empty($groupids)) {
            echo 'No se encontraron groupids en la macro global.';
            return;
        }
    
        // Hacer la solicitud a la API para obtener los hosts de los grupos especificados
        $hosts = $this->zabbixApiRequest($apiUrl, $apiToken, 'host.get', [
            'output' => ['hostid', 'name'],
            'selectGroups' => ['groupid', 'name'],  // Incluimos los grupos asociados
            'groupids' => $groupids
        ]);
    
        // Verificar si se obtuvieron hosts
        if ($hosts === null || empty($hosts)) {
            $hosts = [];
        }
    
        // Pasar los hosts a la vista
        $response = new CControllerResponseData(['hosts' => $hosts]);
        $this->setResponse($response);
    }
    

    // Función para hacer peticiones a la API de Zabbix usando un API Token
    private function zabbixApiRequest($apiUrl, $apiToken, $method, $params) {
        $request = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1,
            'auth' => $apiToken  // Usamos el API token aquí
        ];

        $response = $this->makeApiRequest($apiUrl, $request, $apiToken);

        return isset($response['result']) ? $response['result'] : null;
    }

    // Función para realizar las solicitudes HTTP a la API
    private function makeApiRequest($apiUrl, $request, $apiToken) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiToken // Pasamos el token de API aquí
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
