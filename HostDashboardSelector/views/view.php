<?php
// print errors
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
echo '<link rel="stylesheet" type="text/css" href="/usr/share/zabbix/templates/styles.css">';

//namespace Zabbix;
//use CTable;
//use CCol;
//use CColHeader;
//use CButton;
//use CLink;
//use CDiv;

require_once('/usr/share/zabbix/include/classes/html/CTable.php');
require_once('/usr/share/zabbix/include/classes/html/CCol.php');
require_once('/usr/share/zabbix/include/classes/html/CColHeader.php');
require_once('/usr/share/zabbix/include/classes/html/CButton.php');
require_once('/usr/share/zabbix/include/classes/html/CLink.php');
require_once('/usr/share/zabbix/include/classes/html/CDiv.php');

$table = new CTable();
$col = new CCol();
$colHeader = new CColHeader();
$button = new CButton();
$link = new CLink();
$div = new CDiv();


// Función para obtener el token de autenticación
function getAuthToken($apiUrl, $username, $password) {
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

// Función para realizar las solicitudes HTTP a la API
function makeApiRequest($apiUrl, $request, $apiToken = null) {
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


// Verificar si 'hosts' está definido
if (!isset($data['hosts']) || empty($data['hosts'])) {
    echo 'No se encontraron hosts.';
    return;
}
//echo '<pre>';
//print_r($data['hosts']);
//echo '</pre>';

// Cargar el archivo .json
$configPath = dirname(__DIR__) . '/config.json';
$config = json_decode(file_get_contents($configPath), true);

// Acceder a la URL de la API y al token
$serverUrl = $config['serverUrl'];
$apiUrl = $config['apiUrl'];
$apiToken = $config['apiToken'];

// Obtener el token de autenticación
//$apiToken = getAuthToken($apiUrl, $username, $password);

// Función para hacer solicitudes a la API de Zabbix
function zabbixApiRequest($apiUrl, $apiToken, $method, $params) {
    $request = [
        'jsonrpc' => '2.0',
        'method' => $method,
        'params' => $params,
        //'auth' => $apiToken,
        'id' => 1
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiToken // Usamos el token como Bearer Token
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Función para obtener los problemas de un host y contarlos por severidad
function getHostProblemsBySeverity($apiUrl, $apiToken, $hostid) {
    $problemParams = [
        'output' => ['eventid', 'severity', 'acknowledged', 'name', 'objectid'],
        'hostids' => $hostid,
        'recent' => true,
        'sortfield' => ['eventid'],
        'sortorder' => 'DESC'
    ];

    $problemResponse = zabbixApiRequest($apiUrl, $apiToken, 'problem.get', $problemParams);

    $triggerIds = array_column($problemResponse['result'], 'objectid');

    $triggerParams = [
        'output' => ['triggerid', 'status', 'itemid'],
        'triggerids' => $triggerIds,
        'selectItems' => ['itemid', 'status'],
        'filter' => ['status' => 0]  // 0 significa habilitado, 1 es deshabilitado
    ];

    $triggerResponse = zabbixApiRequest($apiUrl, $apiToken, 'trigger.get', $triggerParams);

    $validTriggers = [];
    foreach ($triggerResponse['result'] as $trigger) {
        $allItemsEnabled = true;
        foreach ($trigger['items'] as $item) {
            if ($item['status'] != 0) {
                $allItemsEnabled = false;
                break;
            }
        }
        if ($allItemsEnabled) {
            $validTriggers[] = $trigger['triggerid'];
        }
    }

    $severityCounts = [
        'Disaster' => 0,
        'High' => 0,
        'Average' => 0,
        'Warning' => 0,
        'Information' => 0,
        'Not classified' => 0
    ];

    if (!empty($problemResponse['result'])) {
        foreach ($problemResponse['result'] as $problem) {
            if (in_array($problem['objectid'], $validTriggers)) {
                switch ($problem['severity']) {
                    case 5: $severityCounts['Disaster']++; break;
                    case 4: $severityCounts['High']++; break;
                    case 3: $severityCounts['Average']++; break;
                    case 2: $severityCounts['Warning']++; break;
                    case 1: $severityCounts['Information']++; break;
                    default: $severityCounts['Not classified']++; break;
                }
            }
        }
    }

    return $severityCounts;
}

function getHostGraphs($apiUrl, $apiToken, $hostid) {
    $params = [
        'output' => ['graphid', 'name'],
        'hostids' => $hostid
    ];

    $response = zabbixApiRequest($apiUrl, $apiToken, 'graph.get', $params);

    return $response['result'] ?? [];
}

$macroResponse = zabbixApiRequest($apiUrl, $apiToken, 'usermacro.get', [
    'globalmacro' => true,
    'output' => ['macro', 'value'],
    'filter' => ['macro' => '{$GROUPIDS}']
]);

$groupids = [];
if (!empty($macroResponse['result'])) {
    $groupids = explode(',', str_replace(' ', '', $macroResponse['result'][0]['value']));
}

if (empty($groupids)) {
    echo 'No se encontraron groupids en la macro global.';
    return;
}


$groupsResponse = zabbixApiRequest($apiUrl, $apiToken, 'hostgroup.get', [
    'output' => ['groupid', 'name'],
    'groupids' => $groupids
]);
//print_r($groupsResponse);

$groupNames = [];
if (!empty($groupsResponse['result'])) {
    foreach ($groupsResponse['result'] as $group) {
        $groupNames[$group['groupid']] = $group['name'];
    }
}

if (empty($groupNames)) {
    echo 'No se encontraron nombres de grupos.';
    return;
}

$groupedHosts = [];

foreach ($data['hosts'] as $host) {
    // Si no tiene grupos o el array de hostgroups está vacío
    if (empty($host['hostgroups'])) {
        $groupId = 'default';
        if (!isset($groupedHosts[$groupId])) {
            $groupedHosts[$groupId] = [
                'name' => 'Default Group',
                'hosts' => []
            ];
        }
        $groupedHosts[$groupId]['hosts'][] = $host;
    } else {
        // Recorremos los hostgroups de cada host
        foreach ($host['hostgroups'] as $group) {
            $groupId = $group['groupid'];
            // Asumimos que $groupNames es un array con los nombres de los grupos
            if (isset($groupNames[$groupId])) {
                if (!isset($groupedHosts[$groupId])) {
                    $groupedHosts[$groupId] = [
                        'name' => $groupNames[$groupId],
                        'hosts' => []
                    ];
                }
                $groupedHosts[$groupId]['hosts'][] = $host;
            }
        }
    }
}


if (empty($groupedHosts)) {
    echo 'No se encontraron hosts en los grupos especificados.';
    return;
}

// Añadir el buscador arriba de la tabla
echo '<div class="search">You can search directly ';
echo '<input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search a host...">';
echo '  Displayed ' . count($data['hosts']) . ' Hosts in ' . count($groupids) . ' groups <br>';

// Añadir la leyenda en horizontal
echo '<div class="legend">';
echo '<span style="color: green;">● Host Ok</span>';
echo '<span style="color: red;">● Host Critical</span>';
echo '<span style="color: orange;">● Warning severity</span>';
echo '<span style="color: yellow;">● Warning severity</span>';
echo '<span style="color: cyan;">● Information severity</span>';
echo '<span >● 📊 Missing Dashboard</span>';
echo '</div>';

$container = new CDiv();
$container->setAttribute('style', 'display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-start; background-color: #0e1012;');

foreach ($groupedHosts as $groupId => $group) {
    $numHosts = count($group['hosts']);

    // Crear una tabla para el grupo
    $table = new CTable();
    $table->setHeader([
        (new CColHeader("[".$groupId . "] - " . $group['name'] . " ($numHosts)"))
            ->setAttribute('colspan', '3')
    ]);

    // Añadir cada host a la tabla
    foreach ($group['hosts'] as $host) {
        $problemsBySeverity = getHostProblemsBySeverity($apiUrl, $apiToken, $host['hostid']);
        $graphs = getHostGraphs($apiUrl, $apiToken, $host['hostid']);

        $problemInfo = " ";
        $hasProblems = false;
        $highestSeverity = -1;

        foreach ($problemsBySeverity as $severity => $count) {
            if ($count > 0) {
                $problemInfo .= "$severity: $count, ";
                $hasProblems = true;
                $severityLevel = array_search($severity, ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster']);
                if ($severityLevel > $highestSeverity) {
                    $highestSeverity = $severityLevel;
                }
            }
        }

        if ($hasProblems) {
            $problemInfo = rtrim($problemInfo, ', ');
        } else {
            $problemInfo = "Host ok";
        }

        $color = 'green';
        if ($hasProblems) {
            switch ($highestSeverity) {
                case 5:
                case 4:
                    $color = 'red';
                    break;
                case 3:
                    $color = 'orange';
                    break;
                case 2:
                    $color = 'yellow';
                    break;
                case 1:
                    $color = 'cyan';
                    break;
                default:
                    $color = 'gray';
                    break;
            }
        }

        // Si el host no tiene gráficos, mostrar solo el icono. Si tiene gráficos, mostrar el enlace al dashboard
        $dashboardStatus = empty($graphs)
            ? "📊"  // Mostrar el icono si no hay gráficos
            : (new CLink('See Dashboard', $serverUrl . '/zabbix.php?action=host.dashboard.view&hostid=' . $host['hostid']))
                ->setAttribute('style', 'color: white; background-color: #5bbbbc; font-size:12px; padding: 10px; border-radius: 5px; text-decoration: none;');

        // Añadir las filas con información de problemas y dashboard o icono
        $table->addRow([
            (new CCol($host['name']))->setAttribute('class', 'host-name')->setAttribute('style', 'text-align: center; padding: 10px 0;'),
            (new CCol($problemInfo))->setAttribute('style', 'text-align: center; padding: 10px 0; color: ' . $color . ';'),
            (new CCol($dashboardStatus))->setAttribute('style', 'text-align: center; font-size:24px; vertical-align: middle;')  // Aquí se mostrará ya sea el ícono o el enlace
        ]);
    }

    // Añadir la tabla y el botón al contenedor
    $groupContainer = new CDiv();
    $groupContainer->addItem($table);

    // Añadir todo el grupo al contenedor principal
    $container->addItem($groupContainer);
}

echo $container->toString();


// Añadir el footer
$manifestPath = 'modules/HostDashboardSelector/manifest.json';
$manifestContent = file_get_contents($manifestPath);
$manifestData = json_decode($manifestContent, true);

$moduleName = $manifestData['name'];
$moduleVersion = $manifestData['version'];
$moduleAuthor = $manifestData['author'];

echo '<footer style="background-color: #2b2b2b; color: white; text-align: center; padding: 20px; margin-top: 20px;">';
echo '    <p>&copy; ' . date("Y") . ' Zabbix SIA. Todos los derechos reservados.</p>';
echo '    <p>Module: ' . htmlspecialchars($moduleName) . ' v' . htmlspecialchars($moduleVersion) . '</p>';
echo '    <p>Autor: ' . htmlspecialchars($moduleAuthor) . '</p>';
echo '    <p>Versión Zabbix ' . ZABBIX_VERSION . '</p>';
echo '</footer>';



////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

?>
<!-- Agregamos los estilos de hover para las filas y tabla -->
<!-- Estilos mejorados para el módulo de Zabbix -->
<style>
    /* Contenedor de búsqueda */
    .search {
        background-color: #2b2b2b;
        margin-bottom: 20px;
        /*margin-left: 10px;*/
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
    }

    /* Contenedor de leyendas */
    .legend {
        display: flex;
        font-size: 14px;
        justify-content: space-around;
        background-color: #2b2b2b;
        margin: 10px;
        border-radius: 8px;
        /*box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);*/
        align-items: center;
        height: 50px;
    }

    /* Campo de búsqueda */
    #searchInput {
        width: 300px;
        padding: 10px;
        border: 2px solid #6d6d6d;
        border-radius: 5px;
        font-size: 16px;
        background-color: #3b3b3b;
        color: #ffffff;
        text-align: center;
        transition: border-color 0.3s;
    }

    #searchInput:focus {
        border-color: #008cba;
        outline: none;
    }

    /* Tabla */
    table {
        width: 95%;
        margin: 20px auto;
        border-collapse: collapse;
        background-color: #2b2b2b;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    /* Encabezado de tabla */
    table thead th {
        background-color: #6d6d6d;
        color: #ffffff;
        font-size: 24px;
        text-align: center;
        padding: 15px;
        border-bottom: 2px solid #444;
    }

    /* Filas de tabla */
    table tr {
        transition: background-color 0.3s;
    }

    table tr:nth-child(even) {
        background-color: #333333;
    }

    table tr:hover {
        background-color: #008cba;
        cursor: pointer;
    }

    /* Celdas de tabla */
    table td {
        text-align: center;
        padding: 12px;
        color: #ffffff;
        border-bottom: 1px solid #444;
    }

    /* Botón personalizado */
    .myButton {
        display: inline-block;
        padding: 10px 15px;
        text-decoration: none;
        color: white;
        background-color: #5bbbbc;
        border-radius: 5px;
        font-size: 16px;
        font-weight: bold;
        text-align: center;
        transition: background-color 0.3s, color 0.3s;
    }

    .myButton:hover {
        background-color: #008cba;
        color: #ffffff;
    }

    footer {
        background-color: #2b2b2b;
    }

    /* Línea divisoria */
    hr {
        margin: 20px 0;
        border: none;
        border-top: 2px solid #6d6d6d;
        width: 100%;
    }
</style>

<!-- Script para el buscador -->
<script>
function filterTable() {
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();

    // Recorremos cada fila de las tablas para ver si coincide con la búsqueda
    var rows = document.getElementsByClassName('host-name');
    for (i = 0; i < rows.length; i++) {
        td = rows[i];
        if (td) {
            txtValue = td.textContent || td.innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                rows[i].parentElement.style.display = "";
            } else {
                rows[i].parentElement.style.display = "none";
            }
        }
    }
}
</script>
