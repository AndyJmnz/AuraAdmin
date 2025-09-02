<?php
session_start();

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: login.php');
    exit;
}

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'aura';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Consultas para obtener estadísticas
function getStats($pdo) {
    // Total de usuarios (excluyendo eliminados)
    $stmt = $pdo->query("SELECT COUNT(*) as total_usuarios FROM users WHERE deleted = 0");
    $total_usuarios = $stmt->fetch()['total_usuarios'];
    
    // Total de roles
    $stmt = $pdo->query("SELECT COUNT(*) as total_roles FROM roles");
    $total_roles = $stmt->fetch()['total_roles'];
    
    // Total de suscripciones activas
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as suscripciones FROM users WHERE subscription_status = 'active' AND deleted = 0");
        $suscripciones = $stmt->fetch()['suscripciones'];
    } catch (Exception $e) {
        $suscripciones = 0; // Si no existe la columna, mostrar 0
    }
    
    // Usuarios registrados por mes (últimos 6 meses)
    try {
        $stmt = $pdo->query("
            SELECT 
                MONTH(created_at) as mes,
                MONTHNAME(created_at) as nombre_mes,
                COUNT(*) as cantidad 
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND deleted = 0
            GROUP BY MONTH(created_at), MONTHNAME(created_at)
            ORDER BY mes
        ");
        $usuarios_por_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $usuarios_por_mes = []; // Si hay error, array vacío
    }
    
    // Distribución por roles
    try {
        $stmt = $pdo->query("
            SELECT 
                r.name as rol,
                COUNT(u.id) as cantidad
            FROM roles r
            LEFT JOIN users u ON r.id = u.role_id AND u.deleted = 0
            GROUP BY r.id, r.name
            ORDER BY cantidad DESC
        ");
        $usuarios_por_rol = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $usuarios_por_rol = []; // Si hay error, array vacío
    }
    
    return [
        'total_usuarios' => $total_usuarios,
        'total_roles' => $total_roles,
        'suscripciones' => $suscripciones,
        'usuarios_por_mes' => $usuarios_por_mes,
        'usuarios_por_rol' => $usuarios_por_rol
    ];
}

$stats = getStats($pdo);

// Manejo de logout
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
    <title>Dashboard Administrativo - Aura</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
             width: 150px; /* Adjust this value based on your logo size */
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
            margin-right: 12px;
            font-size: 1.2rem;
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

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            flex: 1;
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

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
                padding: 1rem;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .nav-item span {
                display: none;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <img src="img/logo.png" alt="Aura Logo" class="logo-img">
        </div>
        
        <nav>
            <a href="#" class="nav-item active">
                <span>Estadísticas</span>
            </a>
            <a href="usuarios.php" class="nav-item">
                <span>Usuarios</span>
            </a>
        
            <a href="suscripciones.php" class="nav-item">
                <span>Suscripciones</span>
            </a>
        </nav>
        
        <button class="logout-btn" onclick="location.href='?logout=1'">
            Cerrar Sesión
        </button>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Panel de Control</h1>
            <p>Bienvenido, <?= htmlspecialchars($_SESSION['user_name']) ?> | Dashboard administrativo de Aura</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_usuarios'] ?></div>
                <div class="stat-label">Total Usuarios</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_roles'] ?></div>
                <div class="stat-label">Roles Activos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?= $stats['suscripciones'] ?></div>
                <div class="stat-label">Suscripciones</div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title">Usuarios por Mes</div>
                <div class="chart-container">
                    <canvas id="usuariosChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-title">Distribución por Roles</div>
                <div class="chart-container">
                    <canvas id="rolesChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-title">Actividad Reciente</div>
                <div class="chart-container">
                    <div style="display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100%; color: #6b7280;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📈</div>
                        <div style="text-align: center;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem;">Sistema Activo</div>
                            <div style="font-size: 0.875rem;">Monitoreando actividad</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Datos para el gráfico de usuarios por mes
        const usuariosData = <?= json_encode($stats['usuarios_por_mes']) ?>;
        
        // Configurar gráfico de usuarios por mes
        const ctxUsuarios = document.getElementById('usuariosChart').getContext('2d');
        new Chart(ctxUsuarios, {
            type: 'bar',
            data: {
                labels: usuariosData.map(item => item.nombre_mes || `Mes ${item.mes}`),
                datasets: [{
                    label: 'Usuarios Registrados',
                    data: usuariosData.map(item => item.cantidad),
                    backgroundColor: 'rgba(139, 92, 246, 0.8)',
                    borderColor: 'rgba(139, 92, 246, 1)',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Datos para el gráfico de roles
        const rolesData = <?= json_encode($stats['usuarios_por_rol']) ?>;
        
        // Configurar gráfico de distribución por roles
        const ctxRoles = document.getElementById('rolesChart').getContext('2d');
        new Chart(ctxRoles, {
            type: 'doughnut',
            data: {
                labels: rolesData.map(item => item.rol),
                datasets: [{
                    data: rolesData.map(item => item.cantidad),
                    backgroundColor: [
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderWidth: 2,
                    borderColor: 'white'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>