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

        // Acceder a la URL de la API y a las credenciales
        $apiUrl = $config['apiUrl'];
        $username = $config['username'];
        $password = $config['password'];

        // Obtener el token de autenticación
        $apiToken = $this->getAuthToken($apiUrl, $username, $password);

        if (!$apiToken) {
            echo 'No se pudo obtener el token de autenticación.';
            return;
        }

        // Obtener la macro global que contiene los groupids
        $macro = $this->zabbixApiRequest($apiUrl, $apiToken, 'usermacro.get', [
            'globalmacro' => true,
            //'output' => 'extend'  // O usa ['macro', 'value'] si prefieres campos específicos
            'output' => ['macro', 'value'],
            'filter' => ['macro' => '{$GROUPIDS}'] // Asegúrate de que el nombre de la macro sea correcto
        ]);

        // Convertir el valor de la macro en un array de groupids
        $groupids = [];
        if (!empty($macro)) {
            $groupids = explode(',', $macro[0]['value']);
        }

        // Verificar si se obtuvieron groupids
        if (empty($groupids)) {
            echo 'No se encontraron groupids en la macro global.';
            return;
        }

        // Hacer la solicitud a la API para obtener los hosts de los grupos especificados
        $hosts = $this->zabbixApiRequest($apiUrl, $apiToken, 'host.get', [
            'output' => ['hostid', 'name'],
            'selectHostGroups' => 'extend',
            //'selectGroups' => ['groupid', 'name'],
            'groupids' => $groupids
        ]);
        //echo '<pre>';
        //print_r($groupids);
        //echo '</pre>';

        //echo '<pre>';
        //print_r($hosts);
        //echo '</pre>';


        // Verificar si se obtuvieron hosts
        if ($hosts === null || empty($hosts)) {
            $hosts = [];
        }

        // Pasar los hosts a la vista
        $response = new CControllerResponseData(['hosts' => $hosts]);
        $this->setResponse($response);
    }

    // Función para obtener el token de autenticación
    private function getAuthToken($apiUrl, $username, $password) {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'user.login',
            'params' => [
                'username' => $username, // Nombre correcto del parámetro
                'password' => $password
            ],
            'id' => 1
        ];

        $response = $this->makeApiRequest($apiUrl, $request);
        //echo 'Respuesta completa de la API: ' . json_encode($response);

        if (!$response || !isset($response['result'])) {
            echo 'Error: No se pudo obtener el token. Respuesta de la API: ' . json_encode($response);
        }

        return $response['result'] ?? null;
    }


    // Función para hacer peticiones a la API de Zabbix
    private function zabbixApiRequest($apiUrl, $apiToken, $method, $params) {
        $request = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1
        ];

        // Realizar la solicitud HTTP
        $response = $this->makeApiRequest($apiUrl, $request, $apiToken);

        return $response['result'] ?? null;
    }


    // Función para realizar las solicitudes HTTP a la API
    private function makeApiRequest($apiUrl, $request, $apiToken = null) {
        //echo "API Token: " . $apiToken . "\n";  // Verificar el valor del token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiToken // Usamos el token como Bearer Token
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error en cURL: ' . curl_error($ch);
        }
        curl_close($ch);
        //echo 'Respuesta completa de la API: ' . $response;

        return json_decode($response, true);
    }

}
