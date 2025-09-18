<?php
// Incluir verificación de autenticación
require_once 'auth_check.php';

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
                
                // Generar una contraseña por defecto
                $defaultPassword = 'Aura2024';
                $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
                
                // Si no se seleccionó rol, usar 'student' por defecto (ID: 2)
                $role_id = !empty($_POST['role_id']) ? $_POST['role_id'] : 2;
                
                // Insertar en tabla users
                $stmt = $pdo->prepare("
                    INSERT INTO users 
                    (name, lastname, email, password, role_id, subscription_status, subscription_type, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['lastname'],
                    $_POST['email'],
                    $hashedPassword,
                    $role_id,
                    $_POST['subscription_status'] ?? 'inactive',
                    $_POST['subscription_type'] ?? 'free'
                ]);
                
                $user_id = $pdo->lastInsertId();
                
                $pdo->commit();
                $mensaje = "Usuario creado exitosamente. Contraseña por defecto: " . $defaultPassword;
            } catch (Exception $e) {
                $pdo->rollBack();
                $mensaje = "Error al crear usuario: " . $e->getMessage();
            }
            break;
            
        case 'editar':
            try {
                $pdo->beginTransaction();
                
                // Actualizar tabla users incluyendo role_id (usar el valor que viene del POST)
                $stmt = $pdo->prepare("UPDATE users SET name=?, lastname=?, email=?, role_id=?, subscription_status=?, subscription_type=? WHERE id=?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['lastname'],
                    $_POST['email'],
                    $_POST['role_id'], // Usar exactamente el valor del formulario
                    $_POST['subscription_status'],
                    $_POST['subscription_type'],
                    $_POST['id']
                ]);
                
                $pdo->commit();
                $mensaje = "Usuario actualizado exitosamente.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $mensaje = "Error al actualizar usuario: " . $e->getMessage();
            }
            break;
            
        case 'eliminar':
            try {
                // Simplemente marcar como eliminado en lugar de borrar físicamente
                $stmt = $pdo->prepare("UPDATE users SET deleted = 1 WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                
                // Opcional: cancelar suscripciones activas
                $stmt = $pdo->prepare("UPDATE subscriptions SET status = 'canceled' WHERE user_id = ? AND status IN ('completed', 'pending')");
                $stmt->execute([$_POST['id']]);
                
                // Redirigir de vuelta a la página en lugar de JSON
                header('Location: usuarios.php');
                exit;
               
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar usuario: ' . $e->getMessage()]);
                exit;
            }
    }
}

// Obtener todos los usuarios con sus roles (consulta corregida)
$stmt = $pdo->query("
     SELECT 
        u.id, 
        u.name,
        u.lastname, 
        u.email, 
        u.role_id,
        u.subscription_status,
        u.subscription_type,
        u.created_at,
        r.name as rol_nombre
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE u.deleted = 0
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
            u.lastname,
            u.email, 
            u.subscription_status,
            u.subscription_type, 
            u.role_id
        FROM users u
        LEFT JOIN user_accounts ua ON u.id = ua.user_id
        WHERE u.id=? AND u.deleted = 0
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
            width: 120px; /* Reducido de 150px */
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
            width: 24px; /* Reducido de 24px */
            height: 24px; /* Reducido de 24px */
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

        .main-content {
            margin-left: 280px;
            padding: 1.5rem;
            width: calc(100% - 280px);
            overflow-x: hidden;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 0 1rem;
            width: 100%;
            max-width: 100%;
        }

        .stat-card {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            padding: 1.5rem;
            border-radius: 20px;
            color: white;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            transform: translateY(0);
            transition: all 0.3s ease;
            min-width: 0; /* Previene desbordamiento */
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
            <img src="img/logo.png" alt="Aura Logo" class="logo-img">
        </div>
        
        <nav>
            <a href="dashboard_admin.php" class="nav-item">
                <img src="img/estadisticas.png" alt="Estadísticas" class="nav-icon">
                <span>Estadísticas</span>
            </a>
            <a href="usuarios.php" class="nav-item active">
                <img src="img/usuarios.png" alt="Usuarios" class="nav-icon">
                <span>Usuarios</span>
            </a>
            <a href="suscripciones.php" class="nav-item">
                <img src="img/suscripciones.png" alt="Suscripciones" class="nav-icon">
                <span>Suscripciones</span>
            </a>
        </nav>
        
        <a href="?logout=1" class="logout-btn">
            <img src="img/logout.png" alt="Cerrar Sesión" class="nav-icon">
            <span>Cerrar Sesión</span>
        </a>
    </div>


    <div class="main-content">
        <div class="header">
            <h1>Gestión de Usuarios</h1>
            <p>Administra todos los usuarios del sistema Aura</p>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($usuarios); ?></div>
                <div class="stat-label">Total Usuarios</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($usuarios, function($u) { return !empty($u['rol_nombre']); })); ?></div>
                <div class="stat-label">Con Roles Asignados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($usuarios, function($u) { return $u['rol_nombre'] === 'admin'; })); ?></div>
                <div class="stat-label">Administradores</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($roles); ?></div>
                <div class="stat-label">Roles Disponibles</div>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo strpos($mensaje, 'Error') !== false ? 'error' : ''; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario -->
        <div class="content-card">
            <h2><?php echo $usuario_editar ? 'Editar Usuario' : 'Crear Nuevo Usuario'; ?></h2>
            
            <form method="POST" action="">
                <input type="hidden" name="accion" value="<?php echo $usuario_editar ? 'editar' : 'crear'; ?>">
                <?php if ($usuario_editar): ?>
                    <input type="hidden" name="id" value="<?php echo $usuario_editar['id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Nombre</label>
                        <input type="text" id="name" name="name" required 
                               value="<?php echo $usuario_editar ? htmlspecialchars($usuario_editar['name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="lastname">Apellido</label>
                        <input type="text" id="lastname" name="lastname" required
                               value="<?php echo $usuario_editar ? htmlspecialchars($usuario_editar['lastname']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required
                               value="<?php echo $usuario_editar ? htmlspecialchars($usuario_editar['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="subscription_status">Estado de Suscripción</label>
                        <select id="subscription_status" name="subscription_status">
                            <option value="active" <?php echo ($usuario_editar && $usuario_editar['subscription_status'] == 'active') ? 'selected' : ''; ?>>Activa</option>
                            <option value="inactive" <?php echo ($usuario_editar && $usuario_editar['subscription_status'] == 'inactive') ? 'selected' : ''; ?>>Inactiva</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subscription_type">Tipo de Suscripción</label>
                        <select id="subscription_type" name="subscription_type">
                            <option value="free" <?php echo ($usuario_editar && $usuario_editar['subscription_type'] == 'free') ? 'selected' : ''; ?>>Gratuita</option>
                            <option value="premium" <?php echo ($usuario_editar && $usuario_editar['subscription_type'] == 'premium') ? 'selected' : ''; ?>>Premium</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="role_id">Rol</label>
                        <select id="role_id" name="role_id">
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['id']; ?>" 
                                        <?php 
                                        // Seleccionar 'student' por defecto si no hay usuario editando
                                        if (!$usuario_editar && $rol['id'] == 2) echo 'selected';
                                        // Mantener selección actual si está editando
                                        if ($usuario_editar && $usuario_editar['role_id'] == $rol['id']) echo 'selected';
                                        ?>>
                                    <?php echo htmlspecialchars($rol['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                
                <div style="margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $usuario_editar ? 'Actualizar Usuario' : 'Crear Usuario'; ?>
                    </button>
                    
                    <?php if ($usuario_editar): ?>
                        <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabla de usuarios -->
        <div class="content-card">
            <h2>Lista de Usuarios</h2>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado Suscripción</th>
                            <th>Tipo Suscripción</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['name']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td>
                                    <?php if ($usuario['rol_nombre']): ?>
                                        <span class="badge badge-info">
                                            <?php echo htmlspecialchars($usuario['rol_nombre']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Sin rol</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $usuario['subscription_status'] === 'active' ? '✔️ Activa' : '❌ Inactiva'; ?>
                                </td>
                                <td>
                                    <?php echo $usuario['subscription_type'] === 'free' ? 'Gratuita' : 'Pagada'; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($usuario['created_at'])); ?></td>
                                
                                <td>
                                    <div class="actions">
                                        <a href="usuarios.php?editar=<?php echo $usuario['id']; ?>" 
                                           class="btn btn-secondary btn-small">
                                            Editar
                                        </a>
                                        
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-small"
                                                    onclick="return confirm('¿Estás seguro de eliminar este usuario?')">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($usuarios)): ?>
                    <div style="text-align: center; padding: 2rem; color: #6b7280;">
                        No hay usuarios registrados aún.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const mensaje = document.querySelector('.mensaje');
            if (mensaje) {
                mensaje.style.opacity = '0';
                mensaje.style.transition = 'opacity 0.5s ease';
                setTimeout(() => mensaje.remove(), 500);
            }
        }, 5000);

        // Función para eliminar usuario
        async function eliminarUsuario(id) {
            if (confirm('¿Estás seguro de que deseas eliminar este usuario?')) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'eliminar');
                    formData.append('id', id);
                    
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Mostrar mensaje de éxito
                        showAlert('Usuario eliminado exitosamente', 'success');
                        
                        // IMPORTANTE: Recargar la tabla de usuarios
                        cargarUsuarios(); // O como se llame tu función para cargar usuarios
                        
                        // También recargar las estadísticas si las tienes
                        cargarEstadisticas(); // Si tienes una función para esto
                        
                    } else {
                        showAlert('Error: ' + result.message, 'error');
                    }
                    
                } catch (error) {
                    console.error('Error:', error);
                    showAlert('Error al eliminar usuario', 'error');
                }
            }
        }

        // Función para mostrar alertas (si no la tienes)
        function showAlert(message, type) {
            // Crear o buscar el contenedor de alertas
            let alertContainer = document.getElementById('alertContainer');
            if (!alertContainer) {
                alertContainer = document.createElement('div');
                alertContainer.id = 'alertContainer';
                document.body.insertBefore(alertContainer, document.body.firstChild);
            }
            
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alertContainer.innerHTML = '';
            alertContainer.appendChild(alert);
            
            // Quitar la alerta después de 5 segundos
            setTimeout(() => alert.remove(), 5000);
        }
    </script>
</body>
</html>