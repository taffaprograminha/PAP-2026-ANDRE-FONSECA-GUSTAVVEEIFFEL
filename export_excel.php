<?php
session_start();
require_once 'ligacao.php';

if (!isset($_SESSION['email'])) {
    http_response_code(401);
    exit('Não autenticado');
}

$period = $_GET['period'] ?? '7d';
if (!in_array($period, ['today','7d','30d','all'], true)) $period = '7d';

switch ($period) {
    case 'today':
        $whereLeit = "DATE(l.data_leitura) = CURDATE()";
        $whereUni  = "DATE(data_leitura) = CURDATE()";
        $label = "Hoje";
        break;
    case '30d':
        $whereLeit = "l.data_leitura >= NOW() - INTERVAL 30 DAY";
        $whereUni  = "data_leitura >= NOW() - INTERVAL 30 DAY";
        $label = "Últimos 30 dias";
        break;
    case 'all':
        $whereLeit = "1=1";
        $whereUni  = "1=1";
        $label = "Sempre";
        break;
    default:
        $whereLeit = "l.data_leitura >= NOW() - INTERVAL 7 DAY";
        $whereUni  = "data_leitura >= NOW() - INTERVAL 7 DAY";
        $label = "Últimos 7 dias";
        break;
}

$fname = "estatisticas_rfid_".date('Ymd_His').".xls";

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');

// escapa para XML (& < > " ')
function x(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}
function cell($v, string $type = 'String', ?string $style = null): string {
    $st = $style ? ' ss:StyleID="'.$style.'"' : '';
    return "<Cell$st><Data ss:Type=\"$type\">".x((string)$v)."</Data></Cell>";
}
function emptyCell(?string $style = null): string {
    $st = $style ? ' ss:StyleID="'.$style.'"' : '';
    return "<Cell$st/>";
}

echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
echo '<?mso-application progid="Excel.Sheet"?>'."\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">

 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Title>Estatísticas RFID</Title>
  <Author>Sistema RFID</Author>
  <Created><?php echo date('c'); ?></Created>
 </DocumentProperties>

 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#18181B"/>
   <Alignment ss:Vertical="Center"/>
  </Style>

  <Style ss:ID="title">
   <Font ss:FontName="Calibri" ss:Size="18" ss:Bold="1" ss:Color="#6E56CF"/>
   <Alignment ss:Vertical="Center"/>
  </Style>

  <Style ss:ID="subtitle">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Italic="1" ss:Color="#5C5A54"/>
  </Style>

  <Style ss:ID="header">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#6E56CF" ss:Pattern="Solid"/>
   <Alignment ss:Vertical="Center" ss:Horizontal="Left"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#5A43B8"/>
   </Borders>
  </Style>

  <Style ss:ID="cell">
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EAE8E2"/>
   </Borders>
  </Style>
  <Style ss:ID="zebra">
   <Interior ss:Color="#F8F7F3" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EAE8E2"/>
   </Borders>
  </Style>

  <Style ss:ID="permitido">
   <Font ss:Bold="1" ss:Color="#1D5C3D"/>
   <Interior ss:Color="#DCEFE3" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EAE8E2"/></Borders>
  </Style>
  <Style ss:ID="negado">
   <Font ss:Bold="1" ss:Color="#7A1F15"/>
   <Interior ss:Color="#F8DCD8" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EAE8E2"/></Borders>
  </Style>
  <Style ss:ID="desconhecido">
   <Font ss:Bold="1" ss:Color="#6E4400"/>
   <Interior ss:Color="#FCEAC8" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EAE8E2"/></Borders>
  </Style>
  <Style ss:ID="online">
   <Font ss:Bold="1" ss:Color="#1D5C3D"/>
   <Interior ss:Color="#DCEFE3" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center"/>
   <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EAE8E2"/></Borders>
  </Style>
  <Style ss:ID="offline">
   <Font ss:Bold="1" ss:Color="#7A1F15"/>
   <Interior ss:Color="#F8DCD8" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center"/>
   <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EAE8E2"/></Borders>
  </Style>

  <Style ss:ID="code">
   <Font ss:FontName="Menlo" ss:Size="10" ss:Color="#5C5A54"/>
   <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EAE8E2"/></Borders>
  </Style>
  <Style ss:ID="codeZebra">
   <Font ss:FontName="Menlo" ss:Size="10" ss:Color="#5C5A54"/>
   <Interior ss:Color="#F8F7F3" ss:Pattern="Solid"/>
   <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EAE8E2"/></Borders>
  </Style>

  <Style ss:ID="num">
   <Font ss:Bold="1"/>
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EAE8E2"/></Borders>
  </Style>
  <Style ss:ID="numZebra">
   <Font ss:Bold="1"/>
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Interior ss:Color="#F8F7F3" ss:Pattern="Solid"/>
   <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EAE8E2"/></Borders>
  </Style>

  <Style ss:ID="pct">
   <Font ss:Bold="1" ss:Color="#6E56CF"/>
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <NumberFormat ss:Format="0.0&quot;%&quot;"/>
   <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EAE8E2"/></Borders>
  </Style>

  <Style ss:ID="muted">
   <Font ss:Color="#9A978D" ss:Italic="1"/>
   <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EAE8E2"/></Borders>
  </Style>
 </Styles>

<?php
// ============================================================
// ABA 1 — Leituras
// ============================================================
?>
 <Worksheet ss:Name="Leituras">
  <Table ss:DefaultRowHeight="18">
   <Column ss:Width="130"/>
   <Column ss:Width="100"/>
   <Column ss:Width="130"/>
   <Column ss:Width="180"/>
   <Column ss:Width="200"/>
   <Column ss:Width="140"/>
   <Column ss:Width="160"/>

   <Row ss:Height="28">
    <Cell ss:MergeAcross="6" ss:StyleID="title"><Data ss:Type="String">Histórico de Leituras RFID</Data></Cell>
   </Row>
   <Row ss:Height="18">
    <Cell ss:MergeAcross="6" ss:StyleID="subtitle"><Data ss:Type="String">Período: <?php echo x($label); ?> · Gerado em <?php echo date('d/m/Y H:i'); ?></Data></Cell>
   </Row>
   <Row/>

   <Row ss:Height="26">
<?php foreach (['Data / Hora','Status','UID Cartão','Utilizador','Email','Placa','Localização'] as $h): ?>
    <?php echo cell($h, 'String', 'header'); ?>

<?php endforeach; ?>
   </Row>
<?php
$sql = "
    SELECT l.data_leitura, l.status, l.cartao_uid,
           u.nome AS user_nome, u.email,
           p.nome AS placa_nome, p.localizacao
    FROM leituras_rfid l
    LEFT JOIN users_autorizados u ON l.user_id = u.id
    JOIN placas_arduino p ON l.placa_id = p.id
    WHERE $whereUni
    ORDER BY l.data_leitura DESC
";
$res = $conn->query($sql);
$i = 0;
while ($row = $res->fetch_assoc()) {
    $zebra = ($i++ % 2) === 1;
    $base = $zebra ? 'zebra' : 'cell';
    $codeStyle = $zebra ? 'codeZebra' : 'code';
    $dt = date('d/m/Y H:i', strtotime($row['data_leitura']));
    echo "   <Row>\n";
    echo "    ".cell($dt, 'String', $base)."\n";
    echo "    ".cell(ucfirst($row['status']), 'String', $row['status'])."\n";
    echo "    ".cell($row['cartao_uid'], 'String', $codeStyle)."\n";
    echo "    ".cell($row['user_nome'] ?? 'Desconhecido', 'String', $row['user_nome'] ? $base : 'muted')."\n";
    echo "    ".cell($row['email'] ?? '', 'String', $base)."\n";
    echo "    ".cell($row['placa_nome'], 'String', $base)."\n";
    echo "    ".cell($row['localizacao'] ?? '', 'String', $base)."\n";
    echo "   </Row>\n";
}
?>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <FreezePanes/>
   <FrozenNoSplit/>
   <SplitHorizontal>4</SplitHorizontal>
   <TopRowBottomPane>4</TopRowBottomPane>
   <ActivePane>2</ActivePane>
  </WorksheetOptions>
 </Worksheet>

<?php
// ============================================================
// ABA 2 — Utilizadores
// ============================================================
$sqlUsers = "
    SELECT u.id, u.nome, u.email, u.rfid_uid, u.ativo,
           MAX(l.data_leitura) AS ultimo,
           COUNT(l.id) AS total_acessos
    FROM users_autorizados u
    LEFT JOIN leituras_rfid l ON l.user_id = u.id AND l.status = 'permitido'
    GROUP BY u.id
    ORDER BY u.nome
";
?>
 <Worksheet ss:Name="Utilizadores">
  <Table ss:DefaultRowHeight="18">
   <Column ss:Width="50"/>
   <Column ss:Width="200"/>
   <Column ss:Width="220"/>
   <Column ss:Width="140"/>
   <Column ss:Width="80"/>
   <Column ss:Width="140"/>
   <Column ss:Width="120"/>

   <Row ss:Height="28">
    <Cell ss:MergeAcross="6" ss:StyleID="title"><Data ss:Type="String">Utilizadores Autorizados</Data></Cell>
   </Row>
   <Row ss:Height="18">
    <Cell ss:MergeAcross="6" ss:StyleID="subtitle"><Data ss:Type="String">Total cumulativo de acessos permitidos · Gerado em <?php echo date('d/m/Y H:i'); ?></Data></Cell>
   </Row>
   <Row/>

   <Row ss:Height="26">
<?php foreach (['ID','Nome','Email','UID Cartão','Estado','Último acesso','Acessos totais'] as $h): ?>
    <?php echo cell($h, 'String', 'header'); ?>

<?php endforeach; ?>
   </Row>
<?php
$res = $conn->query($sqlUsers);
$i = 0;
while ($row = $res->fetch_assoc()) {
    $zebra = ($i++ % 2) === 1;
    $base = $zebra ? 'zebra' : 'cell';
    $codeStyle = $zebra ? 'codeZebra' : 'code';
    $numStyle  = $zebra ? 'numZebra' : 'num';
    $ultimoTxt = $row['ultimo'] ? date('d/m/Y H:i', strtotime($row['ultimo'])) : 'nunca';
    $ultimoStyle = $row['ultimo'] ? $base : 'muted';
    $estadoStyle = $row['ativo'] ? 'permitido' : 'negado';
    $estadoTxt   = $row['ativo'] ? 'Ativo' : 'Inativo';
    echo "   <Row>\n";
    echo "    ".cell($row['id'], 'Number', $numStyle)."\n";
    echo "    ".cell($row['nome'], 'String', $base)."\n";
    echo "    ".cell($row['email'] ?? '', 'String', $base)."\n";
    echo "    ".cell($row['rfid_uid'], 'String', $codeStyle)."\n";
    echo "    ".cell($estadoTxt, 'String', $estadoStyle)."\n";
    echo "    ".cell($ultimoTxt, 'String', $ultimoStyle)."\n";
    echo "    ".cell((int)$row['total_acessos'], 'Number', $numStyle)."\n";
    echo "   </Row>\n";
}
?>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <FreezePanes/>
   <FrozenNoSplit/>
   <SplitHorizontal>4</SplitHorizontal>
   <TopRowBottomPane>4</TopRowBottomPane>
   <ActivePane>2</ActivePane>
  </WorksheetOptions>
 </Worksheet>

<?php
// ============================================================
// ABA 3 — Placas Arduino
// ============================================================
$sqlPlacas = "
    SELECT p.nome, p.localizacao, p.estado,
           COUNT(l.id) AS total,
           SUM(CASE WHEN l.status='permitido'    THEN 1 ELSE 0 END) AS ok,
           SUM(CASE WHEN l.status='negado'       THEN 1 ELSE 0 END) AS ko,
           SUM(CASE WHEN l.status='desconhecido' THEN 1 ELSE 0 END) AS un
    FROM placas_arduino p
    LEFT JOIN leituras_rfid l ON p.id = l.placa_id AND ($whereLeit)
    WHERE p.ativo = 1
    GROUP BY p.id
    ORDER BY total DESC
";
?>
 <Worksheet ss:Name="Placas">
  <Table ss:DefaultRowHeight="18">
   <Column ss:Width="160"/>
   <Column ss:Width="160"/>
   <Column ss:Width="90"/>
   <Column ss:Width="90"/>
   <Column ss:Width="110"/>
   <Column ss:Width="90"/>
   <Column ss:Width="110"/>
   <Column ss:Width="100"/>

   <Row ss:Height="28">
    <Cell ss:MergeAcross="7" ss:StyleID="title"><Data ss:Type="String">Placas Arduino — Ranking e Distribuição</Data></Cell>
   </Row>
   <Row ss:Height="18">
    <Cell ss:MergeAcross="7" ss:StyleID="subtitle"><Data ss:Type="String">Período: <?php echo x($label); ?> · Gerado em <?php echo date('d/m/Y H:i'); ?></Data></Cell>
   </Row>
   <Row/>

   <Row ss:Height="26">
<?php foreach (['Placa','Localização','Estado','Total','Permitidos','Negados','Desconhecidos','% Sucesso'] as $h): ?>
    <?php echo cell($h, 'String', 'header'); ?>

<?php endforeach; ?>
   </Row>
<?php
$res = $conn->query($sqlPlacas);
$i = 0;
while ($row = $res->fetch_assoc()) {
    $zebra = ($i++ % 2) === 1;
    $base = $zebra ? 'zebra' : 'cell';
    $numStyle = $zebra ? 'numZebra' : 'num';
    $tot = (int)$row['total'];
    $ok  = (int)$row['ok'];
    $taxa = $tot > 0 ? round(($ok / $tot) * 100, 1) : 0;
    echo "   <Row>\n";
    echo "    ".cell($row['nome'], 'String', $base)."\n";
    echo "    ".cell($row['localizacao'] ?? '', 'String', $base)."\n";
    echo "    ".cell(ucfirst($row['estado']), 'String', $row['estado'])."\n";
    echo "    ".cell($tot, 'Number', $numStyle)."\n";
    echo "    ".cell($ok, 'Number', $numStyle)."\n";
    echo "    ".cell((int)$row['ko'], 'Number', $numStyle)."\n";
    echo "    ".cell((int)$row['un'], 'Number', $numStyle)."\n";
    echo "    ".cell($taxa, 'Number', 'pct')."\n";
    echo "   </Row>\n";
}
?>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <FreezePanes/>
   <FrozenNoSplit/>
   <SplitHorizontal>4</SplitHorizontal>
   <TopRowBottomPane>4</TopRowBottomPane>
   <ActivePane>2</ActivePane>
  </WorksheetOptions>
 </Worksheet>
</Workbook>
