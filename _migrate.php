<?php
// Adiciona colunas de preferências à tabela users.
// Só é incluído por ligacao.php quando a coluna ainda não existe.

$prevReport = mysqli_report(MYSQLI_REPORT_OFF); // não lançar exceções aqui

$migrations = [
    "ALTER TABLE users ADD COLUMN email_notifications  TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE users ADD COLUMN digest_notifications TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE users ADD COLUMN dark_mode            TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE users ADD COLUMN timezone             VARCHAR(50) NOT NULL DEFAULT 'Europe/Lisbon'",
    "ALTER TABLE users ADD COLUMN date_format          VARCHAR(5)  NOT NULL DEFAULT 'pt'",
];

foreach ($migrations as $sql) {
    @$conn->query($sql); // erro 1060 (coluna já existe) é ignorado
}

mysqli_report($prevReport);
