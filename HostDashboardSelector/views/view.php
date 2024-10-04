<?php
use CTable;
use CCol;
use CColHeader;
use CButton;
use CLink;
use CDiv;

// Verificar si 'hosts' está definido
if (!isset($data['hosts']) || empty($data['hosts'])) {
    echo 'No se encontraron hosts.';
    return;
}


// Cargar el archivo .json
$configPath = dirname(__DIR__) . '/config.json';
$config = json_decode(file_get_contents($configPath), true);

// Acceder a la URL de la API y al token
$serverUrl = $config['serverUrl'];
$apiUrl = $config['apiUrl'];
$apiToken = $config['apiToken'];

// Función para hacer solicitudes a la API de Zabbix
function zabbixApiRequest($apiUrl, $apiToken, $method, $params) {
    $request = [
        'jsonrpc' => '2.0',
        'method' => $method,
        'params' => $params,
        'auth' => $apiToken,
        'id' => 1
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Función para obtener los problemas de un host y contarlos por severidad
function getHostProblemsBySeverity($apiUrl, $apiToken, $hostid) {
    $params = [
        'output' => ['eventid', 'severity', 'acknowledged', 'name'],
        'hostids' => $hostid,
        'recent' => true,
        'sortfield' => ['eventid'],
        'sortorder' => 'DESC'
    ];

    // Hacer la solicitud a la API
    $response = zabbixApiRequest($apiUrl, $apiToken, 'problem.get', $params);

    // Inicializar un array para contar problemas por severidad
    $severityCounts = [
        'Disaster' => 0,
        'High' => 0,
        'Average' => 0,
        'Warning' => 0,
        'Information' => 0,
        'Not classified' => 0
    ];

    // Contar problemas por severidad
    if (!empty($response['result'])) {
        foreach ($response['result'] as $problem) {
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

    return $severityCounts;
}

function getHostGraphs($apiUrl, $apiToken, $hostid) {
    $params = [
        'output' => ['graphid', 'name'],
        'hostids' => $hostid
    ];

    // Hacer la solicitud a la API para obtener los gráficos del host
    $response = zabbixApiRequest($apiUrl, $apiToken, 'graph.get', $params);

    // Si no hay gráficos devueltos, el host no tiene un dashboard asignado
    return $response['result'] ?? [];
}

// Obtener la macro global que contiene los groupids
$macroResponse = zabbixApiRequest($apiUrl, $apiToken, 'usermacro.get', [
    'globalmacro' => true,
    'output' => ['macro', 'value'],
    'filter' => ['macro' => '{$GROUPIDS}']
]);

$groupids = [];
if (!empty($macroResponse['result'])) {
    $groupids = explode(',', str_replace(' ', '', $macroResponse['result'][0]['value']));  // Convertir el valor de la macro a array
}

// Verificar si se obtuvieron groupids
if (empty($groupids)) {
    echo 'No se encontraron groupids en la macro global.';
    return;
}

// Hacer la solicitud a la API para obtener los nombres de los grupos
$groupsResponse = zabbixApiRequest($apiUrl, $apiToken, 'hostgroup.get', [
    'output' => ['groupid', 'name'],
    'groupids' => $groupids
]);

// Construir el array $groupNames dinámicamente usando los datos obtenidos de la API
$groupNames = [];
if (!empty($groupsResponse['result'])) {
    foreach ($groupsResponse['result'] as $group) {
        $groupNames[$group['groupid']] = $group['name'];
    }
}

// Verificar si tenemos nombres de grupos
if (empty($groupNames)) {
    echo 'No se encontraron nombres de grupos.';
    return;
}

// Crear un array para agrupar hosts por grupo
$groupedHosts = [];

// Recorrer los hosts y agruparlos por groupid
foreach ($data['hosts'] as $host) {
    if (isset($host['groups']) && is_array($host['groups'])) {
        foreach ($host['groups'] as $group) {
            $groupId = $group['groupid'];
            if (isset($groupNames[$groupId])) {
                // Inicializamos el grupo si no existe
                if (!isset($groupedHosts[$groupId])) {
                    $groupedHosts[$groupId] = [
                        'name' => $groupNames[$groupId],
                        'hosts' => []
                    ];
                }
                // Añadimos el host a su grupo correspondiente
                $groupedHosts[$groupId]['hosts'][] = $host;
            }
        }
    }
}

// Verificar si tenemos grupos para mostrar
if (empty($groupedHosts)) {
    echo 'No se encontraron hosts en los grupos especificados.';
    return;
}

// Añadir el buscador arriba de la tabla
echo '<div class="search">You can search directly ';
echo '<input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search a host...">';


// Añadir la leyenda en horizontal
echo '<div class="legend" style="margin-top: 10px; display: flex; justify-content: space-around; font-size: 14px;">';
echo '<span style="color: green;">● Host has a dashboard</span>';
echo '<span style="color: red;">● Host missing dashboard</span>';
echo '<span style="color: orange;">● Warning severity</span>';
echo '<span style="color: yellow;">● Warning severity</span>';
echo '<span style="color: cyan;">● Information severity</span>';
echo '<span >● 📊 Missing Dashboard</span>';
echo '</div>';
echo '</div>';

// Crear la barra divisoria antes de la tabla


// Crear el contenedor principal con diseño en columnas (flexbox)
$container = new CDiv();
$container->setAttribute('style', 'display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-start;');

// Crear una tabla por cada grupo y añadirla al contenedor
foreach ($groupedHosts as $groupId => $group) {
    $numHosts = count($group['hosts']); // Contador de hosts en el grupo
    
    $table = new CTable();
    $table->setHeader([
        (new CColHeader("[".$groupId . "] - " . $group['name'] . " ($numHosts)")) 
            ->setAttribute('colspan', '4')  // Asegurar que ocupe las 2 columnas
            ->setAttribute('style', 'text-align: center; font-size: 24px;')
    ]);

    // Añadir los hosts del grupo a la tabla
    foreach ($group['hosts'] as $host) {
        // Obtener problemas para el host actual, agrupados por severidad
        $problemsBySeverity = getHostProblemsBySeverity($apiUrl, $apiToken, $host['hostid']);
        $graphs = getHostGraphs($apiUrl, $apiToken, $host['hostid']);

        // Crear la cadena que contiene la información de problemas por severidad
        $problemInfo = " ";
        $hasProblems = false;
        $highestSeverity = -1;
        
        foreach ($problemsBySeverity as $severity => $count) {
            if ($count > 0) {
                $problemInfo .= "$severity: $count, ";
                $hasProblems = true;
                // Asignar la severidad más alta encontrada
                $severityLevel = array_search($severity, ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster']);
                if ($severityLevel > $highestSeverity) {
                    $highestSeverity = $severityLevel;
                }
            }
        }
        if ($hasProblems) {
            $problemInfo = rtrim($problemInfo, ', ');  // Eliminar la última coma
        } else {
            $problemInfo = "Host ok";
        }

        // Determinar el color en función de la severidad más alta encontrada
        $color = 'green'; // Por defecto si no hay problemas
        if ($hasProblems) {
            switch ($highestSeverity) {
                case 5: // Disaster
                case 4: // High
                    $color = 'red';
                    break;
                case 3: // Average
                    $color = 'orange';
                    break;
                case 2: // Warning
                    $color = 'yellow';
                    break;
                case 1: // Information
                    $color = 'cyan';
                    break;
                default: // Not classified
                    $color = 'gray';
                    break;
            }
        }


        // Si el host no tiene gráficos ni triggers, se considera que no tiene dashboard asignado
        $dashboardStatus = empty($graphs) ? "📊" : "";
        $dashColor = empty($graphs) ? "Red" : "Green";
        


        // Crear el enlace al dashboard del host
        $dashboard_url = $serverUrl . '/zabbix.php?action=host.dashboard.view&hostid=' . $host['hostid'];
        $link = (new CLink('Ver Dashboard', $dashboard_url))
                    ->setAttribute('style', 'color: white; background-color: #5bbbbc; padding: 10px; border-radius: 5px; text-decoration: none;');

        // Añadir las filas con información de problemas y dashboard
        $table->addRow([
            (new CCol($host['name']))->setAttribute('class', 'host-name')->setAttribute('style', 'text-align: center; padding: 10px 0;'),
            (new CCol($problemInfo))->setAttribute('style', 'text-align: center; padding: 10px 0; color: ' . $color . ';'),
            (new CCol($link))->setAttribute('style', 'text-align: center; padding: 10px 0;'),
            (new CCol($dashboardStatus))->setAttribute('style', 'text-align: center; font-size: 24px; vertical-align: middle;')
        ]);
    }

    // Añadir cada tabla (columna) al contenedor principal
    $container->addItem($table);
}

// Mostrar el contenedor con las tablas de grupos en columnas
echo $container->toString();
?>

<!-- Agregamos los estilos de hover para las filas y tabla -->
<style>
    .search {
        padding-left: 50px;
        background-color: #2b2b2b;
        margin-bottom: 20px;
    }
    .legend {
        background-color: #2b2b2b;
        margin: 20px;
    }

    #searchInput{
        width: 300px; 
        padding: 10px;
        text-align: "center";
    }

    table {
        margin-left: 20px;
        background-color: #2b2b2b;
    }
    table thead th {
        background-color: #6d6d6d; /* Color de fondo oscuro similar al modo oscuro de Zabbix */
        color: #ffffff;            /* Color del texto (blanco) */
        font-size: 28px;           /* Tamaño de fuente más grande para el título */
        text-align: center;        /* Centrar el texto */
        padding: 20px;             /* Espaciado interno */
        border-bottom: 2px solid #444; /* Línea inferior más gruesa para el borde */
    }

    table tr {
        transition: background-color 0.3s;
        padding: 30px;             /* Espaciado interno */
    }

    table tr:hover {
        background-color: #008cba; /* Color de fondo al pasar el ratón sobre la fila */
        cursor: pointer;
    }

    /* Hover para toda la fila */
    table td:hover {
        background-color: #008cba;
    }

    /* Estilo para el botón de dashboard */
    .myButton:hover {
        background-color: #008cba;
        color: black;
    }

    /* Estilo consistente para el botón */
    .myButton {
        display: inline-block;
        padding: 10px;
        text-decoration: none;
        color: white;
        background-color: #5bbbbc;
        border-radius: 5px;
        text-align: center;
        transition: background-color 0.3s;
    }

    .myButton:hover {
        background-color: #008cba;
    }
    hr {
        margin-bottom: 20px;
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
