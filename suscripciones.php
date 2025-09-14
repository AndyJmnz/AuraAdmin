<?php
session_start();

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'aura';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_subscriptions':
            try {
                $search = $_POST['search'] ?? '';
                $status_filter = $_POST['status_filter'] ?? '';
                
                
                // Construir query con filtros
                $where_conditions = [];
                $params = [];
                
                if (!empty($search)) {
                    $where_conditions[] = "(u.name LIKE ? OR u.lastname LIKE ? OR u.email LIKE ? OR s.transaction_id LIKE ?)";
                    $search_param = "%$search%";
                    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
                }
                
                if (!empty($status_filter)) {
                    $where_conditions[] = "s.status = ?";
                    $params[] = $status_filter;
                }
                
                
                $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
                
                // Get all records without pagination
                $sql = "SELECT s.*, u.name, u.lastname, u.email 
                       FROM subscriptions s 
                       LEFT JOIN users u ON s.user_id = u.id 
                       $where_clause 
                       ORDER BY s.created_at DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $subscriptions
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        case 'get_users':
            try {
                $stmt = $pdo->prepare("SELECT id, name, lastname, email FROM user WHERE deleted = 0 ORDER BY name");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        case 'get_stats':
            try {
                // Total subscripciones
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE status != 'canceled'");
                $stmt->execute();
                $totalSubs = $stmt->fetchColumn();
                
                // Activas/Completadas
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE status = 'completed'");
                $stmt->execute();
                $activeSubs = $stmt->fetchColumn();
                
                // Fallidas
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE status = 'failed'");
                $stmt->execute();
                $failedSubs = $stmt->fetchColumn();
                
                // Ingresos del mes actual
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(amount), 0) 
                    FROM subscriptions 
                    WHERE status = 'completed' 
                    AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
                    AND YEAR(created_at) = YEAR(CURRENT_DATE())
                ");
                $stmt->execute();
                $monthlyRevenue = $stmt->fetchColumn();
                
                $stats = [
                    'totalSubs' => $totalSubs,
                    'activeSubs' => $activeSubs,
                    'failedSubs' => $failedSubs,
                    'monthlyRevenue' => $monthlyRevenue
                ];
                
                // FALTABA ESTA LÍNEA:
                echo json_encode(['success' => true, 'data' => $stats]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        case 'create_subscription':
            try {
                $sql = "INSERT INTO subscriptions (plan_name, amount, currency, payment_method, transaction_id, status, user_id, start_date, end_time, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_POST['plan_name'],
                    $_POST['amount'],
                    $_POST['currency'],
                    $_POST['payment_method'],
                    $_POST['transaction_id'],
                    $_POST['status'],
                    $_POST['user_id'],
                    $_POST['start_date'],
                    $_POST['end_date']
                ]);
                
                // Actualizar estado en tabla user
                $update_user = "UPDATE users SET subscription_status = ?, subscription_type = ?, subscription_start = ?, subscription_end = ? WHERE id = ?";
                $pdo->prepare($update_user)->execute([
                    $_POST['status'],
                    $_POST['plan_name'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['user_id']
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Subscripción creada exitosamente']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        case 'update_subscription':
            try {
                $sql = "UPDATE subscriptions SET plan_name=?, amount=?, currency=?, payment_method=?, transaction_id=?, status=?, user_id=?, start_date=?, end_time=? WHERE id=?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_POST['plan_name'],
                    $_POST['amount'],
                    $_POST['currency'],
                    $_POST['payment_method'],
                    $_POST['transaction_id'],
                    $_POST['status'],
                    $_POST['user_id'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['id']
                ]);
                
                // Actualizar estado en tabla user
                $update_user = "UPDATE users SET subscription_status = ?, subscription_type = ?, subscription_start = ?, subscription_end = ? WHERE id = ?";
                $pdo->prepare($update_user)->execute([
                    $_POST['status'],
                    $_POST['plan_name'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['user_id']
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Subscripción actualizada exitosamente']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        case 'delete_subscription':
            try {
                $id = $_POST['id'];
                
                // Obtener info de la subscripción antes de borrar
                $get_sub = $pdo->prepare("SELECT user_id FROM subscriptions WHERE id = ?");
                $get_sub->execute([$id]);
                $subscription = $get_sub->fetch(PDO::FETCH_ASSOC);
                
                if ($subscription) {
                    // Eliminar subscripción
                    $stmt = $pdo->prepare("DELETE FROM subscriptions WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Actualizar usuario a estado 'none'
                    $update_user = "UPDATE user SET subscription_status = 'none', subscription_type = 'free', subscription_start = NULL, subscription_end = NULL WHERE id = ?";
                    $pdo->prepare($update_user)->execute([$subscription['user_id']]);
                    
                    echo json_encode(['success' => true, 'message' => 'Subscripción eliminada exitosamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Subscripción no encontrada']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        case 'get_subscription':
            try {
                // Hacer JOIN con la tabla users para obtener también el nombre
                $stmt = $pdo->prepare("
                    SELECT s.*, u.name, u.lastname, u.email 
                    FROM subscriptions s 
                    LEFT JOIN users u ON s.user_id = u.id 
                    WHERE s.id = ?
                ");
                $stmt->execute([$_POST['id']]);
                $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $subscription]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    exit;
}

// Get initial stats
try {
    // Total subscripciones
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE status != 'canceled'");
    $stmt->execute();
    $totalSubs = $stmt->fetchColumn();
    
    // Activas/Completadas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE status = 'completed'");
    $stmt->execute();
    $activeSubs = $stmt->fetchColumn();
    
    // Fallidas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE status = 'failed'");
    $stmt->execute();
    $failedSubs = $stmt->fetchColumn();
    
    // Ingresos del mes actual
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) 
        FROM subscriptions 
        WHERE status = 'completed' 
        AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute();
    $monthlyRevenue = $stmt->fetchColumn();
    
    $stats = [
        'totalSubs' => $totalSubs,
        'activeSubs' => $activeSubs,
        'failedSubs' => $failedSubs,
        'monthlyRevenue' => $monthlyRevenue
    ];
} catch (Exception $e) {
    $stats = [
        'totalSubs' => 0,
        'activeSubs' => 0,
        'failedSubs' => 0,
        'monthlyRevenue' => 0
    ];
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aura - Administrador de Subscripciones</title>
    <link rel="icon" type="image/png" href="img/logo.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #E6E2D2 0%, #E6E2D2 100%);
            min-height: 100vh;
            display: flex;
        }

        .container {
            margin-left: 280px;
            padding: 2rem;
            width: calc(100% - 280px);
        }

        .header {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #7c3aed;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            padding: 2rem;
            border-radius: 20px;
            color: white;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            transform: translateY(0);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #ef4444cc 0%, #ef4444cc 100%);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.5rem;
        }

        .chart-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .chart-title {
            color: #374151;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            text-align: center;
        }

        .chart-container {
            position: relative;
            height: 200px;
        }


        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-container {
            flex: 1;
            max-width: 400px;
        }

        .search-input {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .filter-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-select, .btn {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 20px;
            background: white;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            font-weight: 500;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
        }

        .btn-danger {
            background: linear-gradient(135deg, #fa709a, #fee140);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffecd2, #fcb69f);
        }

        .table-container {
            background: white;
            border-radius: 15px;
            overflow: auto; /* Changed from hidden to auto */
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            max-height: 600px; /* Added max-height for scroll */
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: #f9fafb;
            color: #374151;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 1px solid #e5e7eb;
        }

        .table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        .table tbody tr {
            transition: background-color 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: #f8f9ff;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active { 
            background: #dcfce7; 
            color: #166534; 
        }

        .status-cancelled { 
            background: #fecaca; 
            color: #991b1b; 
        }

        .status-expired { 
            background: #fef3c7; 
            color: #92400e; 
        }

        .status-none { 
            background: #e2e3e5; 
            color: #383d41; 
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal h2 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group input, .form-group select {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination button {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pagination button:hover:not(:disabled) {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            border-left: 4px solid;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }

        /* Sidebar styles */
        .sidebar {
            background: linear-gradient(180deg, #8b5cf6 0%, #7c3aed 100%);
            width: 280px;
            padding: 2rem;
            color: white;
            position: fixed;
            height: 100vh;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }

        .logo {
            display: flex;
            align-items: center;
             padding-left: 2rem;
            margin-bottom: 3rem;
        }

        .logo-img {
            width: 120px;
            height: auto;
            object-fit: contain;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: white;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }

        .nav-item.active {
            background: rgba(255,255,255,0.15);
        }

        .nav-icon {
            width: 24px;
            height: 24px;
            margin-right: 12px;
        }

        .logout-btn {
            position: absolute;
            bottom: 2rem;
            left: 2rem;
            right: 2rem;
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            padding: 1rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.2);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
                padding: 1rem;
            }
            
            .nav-item span,
            .logout-btn span {
                display: none;
            }
            
            .container {
                margin-left: 80px;
                width: calc(100% - 80px);
            }
            
            .logo-img {
                width: 40px;
            }
        }
    </style>
</head>
<body>
    <!-- Add sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="img/logo.png" alt="Aura Logo" class="logo-img">
        </div>
        
        <nav>
            <a href="dashboard_admin.php" class="nav-item">
                <img src="img/estadisticas.png" alt="Estadísticas" class="nav-icon">
                <span>Estadísticas</span>
            </a>
            <a href="usuarios.php" class="nav-item ">
                <img src="img/usuarios.png" alt="Estadísticas" class="nav-icon">
                <span>Usuarios</span>
            </a>
        
            <a href="suscripciones.php" class="nav-item active">
                <img src="img/suscripciones.png" alt="Estadísticas" class="nav-icon">
                <span>Suscripciones</span>
            </a>
        </nav>
        
        <a href="?logout=1" class="logout-btn">
            <img src="img/logout.png" alt="Cerrar Sesión" class="nav-icon">
            <span>Cerrar Sesión</span>
        </a>
    </div>

    <div class="container">
        <div class="header">
            <h1>Suscripciones Aura</h1>
            <p>Administra todas las suscripciones del sistema Aura</p>
        </div>

        <div id="alertContainer"></div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['totalSubs'] ?></div>
                <div class="stat-label">Total Subscripciones</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['activeSubs'] ?></div>
                <div class="stat-label">Activas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['failedSubs'] ?></div>
                <div class="stat-label">Fallidas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?= number_format($stats['monthlyRevenue'], 2) ?></div>
                <div class="stat-label">Ingresos del Mes</div>
            </div>
        </div>

        <div class="controls">
            <div class="search-container">
                <input type="text" class="search-input" id="searchInput" placeholder="🔍 Buscar por usuario, email o ID de transacción...">
            </div>
            
            <div class="filter-container">
                <select class="filter-select" id="statusFilter">
                    <option value="">Todos los Estados</option>
                    <option value="completed">Completadas</option>
                    <option value="pending">Pendientes</option>
                    <option value="failed">Fallidas</option>
                    <option value="canceled">Canceladas</option>
                </select>
                
            </div>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Plan</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Inicio</th>
                        <th>Vencimiento</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="subscriptionsTable">
                    <tr>
                        <td colspan="9" class="loading">Cargando subscripciones...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Commenting out the pagination section
        <div class="pagination" id="pagination">
            <!-- La paginación se generará dinámicamente -->
        <!--</div>-->
    </div>

    <!-- Modal para agregar/editar subscripciones -->
    <div class="modal" id="subscriptionModal">
        <div class="modal-content">
            <h2 id="modalTitle">Nueva Subscripción</h2>
            
            <form id="subscriptionForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="userId">Usuario *</label>
                        <select id="userId" required>
                            <option value="">Seleccionar usuario...</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="planName">Plan *</label>
                        <select id="planName" required>
                            <option value="free">Gratuito</option>
                            <option value="premium">Premium</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Monto *</label>
                        <input type="number" id="amount" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="currency">Moneda *</label>
                        <select id="currency" required>
                            <option value="MXN">MXN - Peso Mexicano</option>
                            <option value="USD">USD - Dólar</option>
                            <option value="EUR">EUR - Euro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="paymentMethod">Método de Pago *</label>
                        <select id="paymentMethod" required>
                            <option value="stripe">Stripe</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="transactionId">ID de Transacción *</label>
                        <input type="text" id="transactionId" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Estado *</label>
                        <select id="status" required>
                            <option value="completed">Completada</option>
                            <option value="pending">Pendiente</option>
                            <option value="failed">Fallida</option>
                            <option value="canceled">Cancelada</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="startDate">Fecha de Inicio *</label>
                        <input type="datetime-local" id="startDate" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="endDate">Fecha de Vencimiento *</label>
                        <input type="datetime-local" id="endDate" required>
                    </div>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: center;">
                    <button type="submit" class="btn btn-success">Guardar</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal()"> Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentPage = 1;
        let totalPages = 1;
        let editingId = null;
        let users = [];

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();
            loadStats();
            loadSubscriptions();
            setupEventListeners();
        });

        function setupEventListeners() {
            document.getElementById('searchInput').addEventListener('input', debounce(loadSubscriptions, 500));
            document.getElementById('statusFilter').addEventListener('change', loadSubscriptions);
            document.getElementById('subscriptionForm').addEventListener('submit', handleSubmit);
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        async function makeRequest(action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            for (let key in data) {
                formData.append(key, data[key]);
            }

            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                return await response.json();
            } catch (error) {
                console.error('Error:', error);
                return { success: false, message: 'Error de conexión' };
            }
        }

        async function loadUsers() {
            const result = await makeRequest('get_users');
            if (result.success) {
                users = result.data;
                populateUserSelect();
            }
        }

        function populateUserSelect() {
            const userSelect = document.getElementById('userId');
            userSelect.innerHTML = '<option value="">Seleccionar usuario...</option>';
            
            users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = `${user.name} ${user.lastname} (${user.email})`;
                userSelect.appendChild(option);
            });
        }

        async function loadStats() {
            try {
                const result = await makeRequest('get_stats');
                if (result.success && result.data) {
                    const stats = result.data;
                    
                    // Buscar los elementos por clase o por el HTML directo
                    const statCards = document.querySelectorAll('.stat-value');
                    
                    if (statCards.length >= 4) {
                        statCards[0].textContent = stats.totalSubs;
                        statCards[1].textContent = stats.activeSubs;
                        statCards[2].textContent = stats.failedSubs;
                        statCards[3].textContent = `$${parseFloat(stats.monthlyRevenue).toLocaleString()}`;
                    }
                    
                    console.log('Stats actualizadas:', stats);
                } else {
                    console.error('Error cargando stats:', result.message);
                }
            } catch (error) {
                console.error('Error en loadStats:', error);
            }
        }

        // Modify the loadSubscriptions function
async function loadSubscriptions() {
    const searchTerm = document.getElementById('searchInput').value;
    const statusFilter = document.getElementById('statusFilter').value;

    const result = await makeRequest('get_subscriptions', {
        search: searchTerm,
        status_filter: statusFilter,
    });

    if (result.success) {
        renderSubscriptions(result.data);
    } else {
        showAlert('Error al cargar subscripciones: ' + result.message, 'error');
    }
}

function renderSubscriptions(subscriptions) {
    const tbody = document.getElementById('subscriptionsTable');
    tbody.innerHTML = '';

    if (subscriptions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="loading">No se encontraron subscripciones</td></tr>';
        return;
    }

    subscriptions.forEach(sub => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${sub.id}</td>
            <td>${sub.name} ${sub.lastname}</td>
            <td>${sub.email}</td>
            <td>${sub.plan_name}</td>
            <td>${sub.currency} ${parseFloat(sub.amount).toLocaleString()}</td>
            <td><span class="status-badge status-${sub.status.toLowerCase()}">${sub.status}</span></td>
            <td>${formatDate(sub.start_date)}</td>
            <td>${formatDate(sub.end_time)}</td>
            <td>
                <div class="actions">
                    <button class="btn btn-secondary btn-small" onclick="openModal('edit', ${sub.id})">
                        Editar
                    </button>
                    <button class="btn btn-danger btn-small" onclick="deleteSubscription(${sub.id})">
                        Eliminar
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

        function openModal(mode, id = null) {
            editingId = id;
            const modal = document.getElementById('subscriptionModal');
            const title = document.getElementById('modalTitle');
            
            title.textContent = mode === 'add' ? 'Nueva Subscripción' : 'Editar Subscripción';
            modal.style.display = 'block';

            if (mode === 'edit' && id) {
                loadSubscriptionDetails(id);
            } else {
                document.getElementById('subscriptionForm').reset();
            }
        }

        async function loadSubscriptionDetails(id) {
            const result = await makeRequest('get_subscription', { id });
            if (result.success) {
                const sub = result.data;
                
                // Para el select de usuario, agregar la opción del usuario actual
                const userSelect = document.getElementById('userId');
                
                // Limpiar el select y mantener solo la opción por defecto
                userSelect.innerHTML = '<option value="">Seleccionar usuario...</option>';
                
                // Agregar la opción del usuario actual con su nombre completo
                const userOption = document.createElement('option');
                userOption.value = sub.user_id;
                userOption.textContent = `${sub.name} ${sub.lastname} (${sub.email})`;
                userSelect.appendChild(userOption);
                
                // Seleccionar el usuario actual
                userSelect.value = sub.user_id;
                
                // Resto de campos
                document.getElementById('planName').value = sub.plan_name;
                document.getElementById('amount').value = sub.amount;
                document.getElementById('currency').value = sub.currency;
                document.getElementById('paymentMethod').value = sub.payment_method;
                document.getElementById('transactionId').value = sub.transaction_id;
                document.getElementById('status').value = sub.status;
                document.getElementById('startDate').value = formatDateForInput(sub.start_date);
                document.getElementById('endDate').value = formatDateForInput(sub.end_time);
            }
        }

        async function handleSubmit(e) {
            e.preventDefault();
            
            const formData = {
                plan_name: document.getElementById('planName').value,
                amount: document.getElementById('amount').value,
                currency: document.getElementById('currency').value,
                payment_method: document.getElementById('paymentMethod').value,
                transaction_id: document.getElementById('transactionId').value,
                status: document.getElementById('status').value,
                user_id: document.getElementById('userId').value,
                start_date: document.getElementById('startDate').value,
                end_date: document.getElementById('endDate').value
            };

    if (editingId) {
        formData.id = editingId;
        const result = await makeRequest('update_subscription', formData);
        handleResponse(result, 'actualizada');
    } else {
        const result = await makeRequest('create_subscription', formData);
        handleResponse(result, 'creada');
    }
}

async function deleteSubscription(id) {
    if (confirm('¿Estás seguro de que deseas eliminar esta subscripción?')) {
        const result = await makeRequest('delete_subscription', { id });
        handleResponse(result, 'eliminada');
    }
}

function handleResponse(result, action) {
    if (result.success) {
        showAlert(`Subscripción ${action} exitosamente`, 'success');
        closeModal();
        loadSubscriptions(); // ← Quitar el parámetro currentPage
        loadStats();
    } else {
        showAlert(`Error: ${result.message}`, 'error');
    }
}

function closeModal() {
    document.getElementById('subscriptionModal').style.display = 'none';
    editingId = null;
}

function showAlert(message, type) {
    const alertContainer = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    alertContainer.innerHTML = '';
    alertContainer.appendChild(alert);
    setTimeout(() => alert.remove(), 5000);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString();
}

function formatDateForInput(dateString) {
    if (!dateString) return '';
    return new Date(dateString).toISOString().slice(0, 16);
}
    </script>
</body>
</html>