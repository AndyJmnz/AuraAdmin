<?php
session_start();

// Configuración de la base de datos

require_once __DIR__ . '/Database.php';
$db = new Database();
$pdo = $db->getConnection();

$mensaje = '';

// Procesar acciones CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'crear':
            try {
                $pdo->beginTransaction();
                
                // Insertar en tabla users
                $stmt = $pdo->prepare("INSERT INTO users (name, email, email_verified_at, password, created_at, updated_at) VALUES (?, ?, NOW(), ?, NOW(), NOW())");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['email'],
                    password_hash($_POST['password'], PASSWORD_DEFAULT)
                ]);
                
                $user_id = $pdo->lastInsertId();
                
                // Insertar en user_accounts si se seleccionó un rol
                if (!empty($_POST['role_id'])) {
                    $stmt = $pdo->prepare("INSERT INTO user_accounts (user_id, role_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                    $stmt->execute([$user_id, $_POST['role_id']]);
                }
                
                $pdo->commit();
                $mensaje = "Usuario creado exitosamente.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $mensaje = "Error al crear usuario: " . $e->getMessage();
            }
            break;
            
        case 'editar':
            try {
                $pdo->beginTransaction();
                
                // Actualizar tabla users
                if (!empty($_POST['password'])) {
                    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, password=?, updated_at=NOW() WHERE id=?");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['email'],
                        password_hash($_POST['password'], PASSWORD_DEFAULT),
                        $_POST['id']
                    ]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, updated_at=NOW() WHERE id=?");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['email'],
                        $_POST['id']
                    ]);
                }
                
                // Actualizar o insertar rol en user_accounts
                if (!empty($_POST['role_id'])) {
                    $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE user_id=?");
                    $stmt->execute([$_POST['id']]);
                    
                    if ($stmt->fetch()) {
                        $stmt = $pdo->prepare("UPDATE user_accounts SET role_id=?, updated_at=NOW() WHERE user_id=?");
                        $stmt->execute([$_POST['role_id'], $_POST['id']]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO user_accounts (user_id, role_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                        $stmt->execute([$_POST['id'], $_POST['role_id']]);
                    }
                }
                
                $pdo->commit();
                $mensaje = "Usuario actualizado exitosamente.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $mensaje = "Error al actualizar usuario: " . $e->getMessage();
            }
            break;
            
        case 'eliminar':
            try {
                $pdo->beginTransaction();
                
                // Eliminar de user_accounts primero
                $stmt = $pdo->prepare("DELETE FROM user_accounts WHERE user_id=?");
                $stmt->execute([$_POST['id']]);
                
                // Eliminar de users
                $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
                $stmt->execute([$_POST['id']]);
                
                $pdo->commit();
                $mensaje = "Usuario eliminado exitosamente.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $mensaje = "Error al eliminar usuario: " . $e->getMessage();
            }
            break;
    }
}

// Obtener todos los usuarios con sus roles
$stmt = $pdo->query("
    SELECT 
        u.id, 
        u.name, 
        u.email, 
        u.email_verified_at, 
        u.created_at,
        r.name as rol_nombre,
        ua.role_id
    FROM users u
    LEFT JOIN user_accounts ua ON u.id = ua.user_id
    LEFT JOIN roles r ON ua.role_id = r.id
    ORDER BY u.created_at DESC
");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los roles para el formulario
$stmt = $pdo->query("SELECT id, name FROM roles ORDER BY name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener usuario para editar
$usuario_editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("
        SELECT 
            u.id, 
            u.name, 
            u.email, 
            ua.role_id
        FROM users u
        LEFT JOIN user_accounts ua ON u.id = ua.user_id
        WHERE u.id=?
    ");
    $stmt->execute([$_GET['editar']]);
    $usuario_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Aura</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            margin-bottom: 3rem;
            font-size: 2rem;
            font-weight: bold;
        }

        .logo-icon {
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 10px;
            margin-right: 15px;
            font-size: 1.5rem;
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

        .content-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #8b5cf6;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin-right: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(139, 92, 246, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(239, 68, 68, 0.4);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            background: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }

        tr:hover {
            background: #f9fafb;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-info {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .mensaje {
            background: #dcfce7;
            color: #166534;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border-left: 4px solid #16a34a;
        }

        .mensaje.error {
            background: #fecaca;
            color: #991b1b;
            border-left-color: #dc2626;
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }

            .table-container {
                font-size: 0.875rem;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <div class="logo-icon">Au</div>
            <span>ra</span>
        </div>
        
        <nav>
            <a href="index.php" class="nav-item">
                <span class="nav-icon">📊</span>
                <span>Estadísticas</span>
            </a>
            <a href="usuarios.php" class="nav-item active">
                <span class="nav-icon">👥</span>
                <span>Usuarios</span>
            </a>
            <a href="roles.php" class="nav-item">
                <span class="nav-icon">🔐</span>
                <span>Roles</span>
            </a>
            <a href="suscripciones.php" class="nav-item">
                <span class="nav-icon">💎</span>