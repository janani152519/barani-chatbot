<?php
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();

// 1. Create MachineDowntime table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS MachineDowntime (
    id INT AUTO_INCREMENT PRIMARY KEY,
    MachineName VARCHAR(50) NOT NULL,
    DowntimeMinutes INT NOT NULL,
    LogDate DATETIME NOT NULL,
    Reason VARCHAR(255) NOT NULL,
    Shift VARCHAR(20) NOT NULL,
    RootCause VARCHAR(255) NULL,
    Created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_machine_date (MachineName, LogDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 2. Create Production table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS Production (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Machine VARCHAR(50) NOT NULL,
    Line VARCHAR(50) NOT NULL,
    Qty INT NOT NULL,
    TargetQty INT NOT NULL DEFAULT 1000,
    HourlyQuota INT NOT NULL DEFAULT 100,
    ScrapRate DECIMAL(5,2) NOT NULL DEFAULT 0.0,
    DefectRate DECIMAL(5,2) NOT NULL DEFAULT 0.0,
    OEE DECIMAL(5,2) NOT NULL DEFAULT 85.0,
    PowerKwh DECIMAL(10,2) NOT NULL DEFAULT 120.0,
    LogDate DATETIME NOT NULL,
    Shift VARCHAR(20) NOT NULL,
    PartFamily VARCHAR(100) NULL,
    BatchNumber VARCHAR(50) NULL,
    ToleranceVariance DECIMAL(5,2) DEFAULT 0.0,
    Created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_prod_machine_date (Machine, LogDate),
    INDEX idx_prod_line_date (Line, LogDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 3. Create MaintenanceLog table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS MaintenanceLog (
    id INT AUTO_INCREMENT PRIMARY KEY,
    AssetId VARCHAR(50) NOT NULL,
    BreakdownCount INT NOT NULL DEFAULT 1,
    MTTRMinutes INT NOT NULL DEFAULT 45,
    LubricationCompliant TINYINT(1) NOT NULL DEFAULT 1,
    LogDate DATETIME NOT NULL,
    Description TEXT NULL,
    Quarter VARCHAR(10) NOT NULL DEFAULT 'Q3',
    Created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_maint_asset (AssetId, LogDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Clear existing benchmark records to avoid duplicate accumulation
$pdo->exec("DELETE FROM MachineDowntime WHERE LogDate >= '2026-08-01'");
$pdo->exec("DELETE FROM Production WHERE LogDate >= '2026-08-01'");
$pdo->exec("DELETE FROM MaintenanceLog WHERE LogDate >= '2026-07-01'");

// Seed MachineDowntime exactly per PDF Page 1 & Page 3
// ML-05: 1,240 min, ML-02: 890 min, ML-06: 620 min, CNC-01: 410 min, HTL-01: 190 min in August 2026
$stmt = $pdo->prepare("INSERT INTO MachineDowntime (MachineName, DowntimeMinutes, LogDate, Reason, Shift, RootCause) VALUES (?, ?, ?, ?, ?, ?)");

$downtimeData = [
    ['ML-05', 740, '2026-08-12 14:30:00', 'Hydraulic valve failure', 'Shift B', 'Hydraulic valve failure on Shift B'],
    ['ML-05', 500, '2026-08-25 09:15:00', 'Pressure seal degradation', 'Shift A', 'Hydraulic pack overheating'],
    ['ML-02', 520, '2026-08-08 11:20:00', 'PLC communication bus fault', 'Shift A', 'Network switch desync'],
    ['ML-02', 370, '2026-08-21 16:45:00', 'Feeder gripper misalignment', 'Shift B', 'Mechanical wear'],
    ['ML-06', 420, '2026-08-14 08:00:00', 'Coolant pump trip', 'Shift A', 'Thermal overload relay tripped'],
    ['ML-06', 200, '2026-08-28 20:10:00', 'Die sensor calibration', 'Shift C', 'Proximity switch replacement'],
    ['CNC-01', 250, '2026-08-05 13:00:00', 'Spindle bearing vibration', 'Shift A', 'High harmonic vibration'],
    ['CNC-01', 160, '2026-08-19 15:30:00', 'Tool changer index jam', 'Shift B', 'Pneumatic cylinder sticking'],
    ['HTL-01', 110, '2026-08-10 10:00:00', 'Conveyor chain slack', 'Shift A', 'Tensioner adjustment'],
    ['HTL-01', 80,  '2026-08-22 18:00:00', 'Heater thermocouple drift', 'Shift B', 'PID sensor calibration']
];
foreach ($downtimeData as $row) {
    $stmt->execute($row);
}

// Seed Production for Multi-turn demo (Page 2):
// ML-06 production output this month (September 2026): 14,850 units
// ML-06 production output last month (August 2026): 13,200 units (Delta: +1,650 units / +12.5%)
$stmtProd = $pdo->prepare("INSERT INTO Production (Machine, Line, Qty, TargetQty, HourlyQuota, ScrapRate, DefectRate, OEE, PowerKwh, LogDate, Shift, PartFamily, BatchNumber, ToleranceVariance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

// ML-06 August records
$stmtProd->execute(['ML-06', 'Line 2', 6600, 6500, 110, 1.2, 0.9, 88.5, 1420.0, '2026-08-15 08:00:00', 'Shift A', 'Flange Series X', 'BAT-2026-0815', 0.8]);
$stmtProd->execute(['ML-06', 'Line 2', 6600, 6500, 110, 1.1, 0.8, 89.2, 1450.0, '2026-08-28 08:00:00', 'Shift B', 'Flange Series X', 'BAT-2026-0828', 0.9]);

// ML-06 September records (current month)
$stmtProd->execute(['ML-06', 'Line 2', 7450, 7000, 125, 0.9, 0.6, 92.4, 1510.0, '2026-09-08 08:00:00', 'Shift A', 'Flange Series X', 'BAT-2026-0908', 0.7]);
$stmtProd->execute(['ML-06', 'Line 2', 7400, 7000, 125, 0.8, 0.5, 93.1, 1500.0, '2026-09-17 08:00:00', 'Shift B', 'Flange Series X', 'BAT-2026-0917', 0.6]);

// Cross-Departmental queries seed data:
// Yesterday's actual production across Line 3 (e.g. 2026-09-17)
$stmtProd->execute(['ML-03', 'Line 3', 1840, 1800, 115, 0.8, 0.7, 91.5, 920.0, '2026-09-17 08:00:00', 'Shift A', 'Shaft Hubs', 'BAT-2026-0917-L3A', 0.5]);
$stmtProd->execute(['ML-04', 'Line 3', 1960, 1800, 120, 0.7, 0.6, 94.2, 980.0, '2026-09-17 16:00:00', 'Shift B', 'Shaft Hubs', 'BAT-2026-0917-L3B', 0.4]);

// Press machine that exceeded hourly quota
$stmtProd->execute(['Press HP-400', 'Line 1', 1450, 1200, 100, 0.5, 0.4, 96.8, 850.0, '2026-09-18 06:00:00', 'Shift A', 'Bracket Casing', 'BAT-2026-0918-P1', 0.3]);
$stmtProd->execute(['Press HP-250', 'Line 1', 950, 1200, 100, 1.4, 1.2, 78.4, 710.0, '2026-09-18 06:00:00', 'Shift A', 'Bracket Casing', 'BAT-2026-0918-P2', 1.1]);

// Line A vs Line B overall capacity utilization
$stmtProd->execute(['Press HP-500', 'Line A', 4850, 5000, 150, 0.8, 0.6, 94.5, 2300.0, '2026-09-17 00:00:00', 'Shift All', 'Stamping Set A', 'BAT-2026-0917-LA', 0.6]);
$stmtProd->execute(['Press HP-300', 'Line B', 4120, 5000, 150, 1.5, 1.3, 82.4, 2100.0, '2026-09-17 00:00:00', 'Shift All', 'Stamping Set B', 'BAT-2026-0917-LB', 1.2]);

// Quality scrap rates and defect rates
$stmtProd->execute(['Die Casting 01', 'Line 4', 2100, 2500, 100, 4.8, 3.9, 74.0, 1800.0, '2026-09-16 14:15:00', 'Shift B', 'Cast Rotor Ring', 'BAT-2026-0916-QR1', 1.8]);
$stmtProd->execute(['Stamping 02', 'Line 2', 3200, 3200, 120, 1.1, 0.9, 89.0, 1100.0, '2026-09-16 14:30:00', 'Shift B', 'Mounting Bracket', 'BAT-2026-0916-MB1', 0.7]);
$stmtProd->execute(['Precision Mill 01', 'Line 5', 850, 900, 50, 5.2, 4.4, 71.5, 950.0, '2026-09-15 14:05:00', 'Shift B', 'Titanium Spindle Hub', 'BAT-2026-0915-TSH', 2.1]);

// Maintenance logs for CNC units in Q3 (July - Sept 2026)
$stmtMaint = $pdo->prepare("INSERT INTO MaintenanceLog (AssetId, BreakdownCount, MTTRMinutes, LubricationCompliant, LogDate, Description, Quarter) VALUES (?, ?, ?, ?, ?, ?, ?)");
$maintRecords = [
    ['CNC-01', 8, 42, 1, '2026-07-15 10:00:00', 'Z-axis ballscrew backlash check', 'Q3'],
    ['CNC-02', 14, 58, 0, '2026-08-04 11:30:00', 'Main spindle motor overheating & coolant flow obstruction', 'Q3'],
    ['CNC-03', 5, 35, 1, '2026-08-19 14:15:00', 'Hydraulic chuck pressure regulator diaphragm change', 'Q3'],
    ['CNC-04', 3, 28, 1, '2026-09-02 09:45:00', 'Automatic tool changer arm positioning sensor', 'Q3'],
    ['ML-05',  11, 64, 0, '2026-08-12 14:30:00', 'Hydraulic valve failure and pump cavitation', 'Q3'],
    ['ML-02',  7, 48, 1, '2026-08-21 16:45:00', 'PLC communication bus fault and feeder rail realignment', 'Q3'],
    ['ML-06',  6, 38, 0, '2026-08-28 20:10:00', 'Coolant filtration blockage & 30-day grease lubrication overdue', 'Q3']
];
foreach ($maintRecords as $mr) {
    $stmtMaint->execute($mr);
}

echo "Database seeded with Enterprise Specification benchmarks successfully!\n";
