<?php
session_start();

// Si ya está logueado y es admin, redirigir al dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
    header('Location: dashboard_admin.php');
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

$error = '';

// Procesar el login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor completa todos los campos.';
    } else {
        try {
            // Buscar usuario y verificar que tenga rol de admin (role_id = 1)
            $stmt = $pdo->prepare("
                SELECT 
                    u.id, 
                    u.name, 
                    u.lastname,
                    u.email, 
                    u.password,
                    u.role_id,
                    r.name as role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.email = ? AND u.role_id = 1 AND u.deleted = 0
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Login exitoso
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role_name'] = $user['role_name'];
                
                // Actualizar último acceso
                //$stmt = $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
                //$stmt->execute([$user['id']]);
                
                header('Location: dashboard_admin.php');
                exit;
            } else {
                $error = 'Credenciales incorrectas o no tienes permisos de administrador.';
            }
        } catch (Exception $e) {
            $error = 'Error en el sistema: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AURA - Iniciar Sesión</title>
    <!-- Agregar esta línea para el favicon -->
    <link rel="icon" type="image/png" href="img/logo.png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #e7e1cf;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .main-container {
            display: flex;
            background: white;
            border-radius: 27px;
            overflow: hidden;
            width: 90%; /* Reducido de 100% */
            max-width: 1000px; /* Reducido de 1200px */
            min-height: 600px; /* Reducido de 700px */
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            position: relative;
        }

        /* Sección izquierda */
        .left-section {
            flex: 1;
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding: 2rem;
        }

        .background-shape {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .students-image {
            width: 75%; /* Reducido de 85% */
            height: auto;
            max-height: 350px; /* Reducido de 400px */
            object-fit: contain;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .slogan {
            color: white;
            font-size: 1.5rem;
            text-align: center;
            font-weight: 200;
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        /* Sección derecha */
        .right-section {
            flex: 1;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem;
        }

        .login-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            width: 90%; /* Reducido de 100% */
            max-width: 350px; /* Reducido de 400px */
            margin: 0 auto;
            text-align: center;
        }

        .login-title {
            font-size: 3.25rem;
            font-weight: 200;
            color: #d987ba;
            margin-bottom: 0.5rem;
            line-height: 1.1;
        }

        .login-subtitle {
            font-size: 1rem;
            font-weight: 200;
            color: #919191;
            margin-bottom: 2.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            width: 100%;
        }

        .form-group input {
            width: 100%;
            background-color: #DDD7C2;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1.125rem;
            color: #919191;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            background-color: #d4c7aa;
            box-shadow: 0 0 0 3px rgba(217, 135, 186, 0.2);
            transform: translateY(-1px);
        }

        .form-group input::placeholder {
            color: #919191;
            font-weight: 200;
        }

        .password-container {
            position: relative;
            width: 100%;
        }

        .password-input {
            padding-right: 3rem !important;
        }

        .eye-button {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            color: #919191;
            font-size: 1.2rem;
            transition: color 0.2s ease;
        }

        .eye-button:hover {
            color: #d987ba;
        }

        .forgot-password {
            text-align: right;
            margin-bottom: 1.25rem;
            margin-top: 0.5rem;
        }

        .forgot-password a {
            color: #919191;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .forgot-password a:hover {
            color: #d987ba;
        }

        .login-btn {
            width: 100%;
            background-color: #f5b764;
            border: none;
            border-radius: 8px;
            padding: 0.9375rem 1rem;
            font-size: 1.25rem;
            font-weight: 200;
            color: #11181C;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
            font-family: inherit;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-btn:hover {
            background-color: #f2ab4f;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 183, 100, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .register-link {
            margin-top: 1rem;
            text-align: center;
        }

        .register-link a {
            color: #9068d9;
            font-size: 0.875rem;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .register-link a:hover {
            color: #7c3aed;
            text-decoration: underline;
        }

        .error-message {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #dc2626;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 200;
            border-left: 4px solid #ef4444;
            animation: slideInError 0.3s ease-out;
        }

        @keyframes slideInError {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
                margin: 1rem;
                max-width: 85%; /* Reducido de 90% */
                min-height: auto;
            }
            
            .left-section {
                padding: 1.5rem 1rem;
                min-height: 250px; /* Reducido de 300px */
            }
            
            .right-section {
                padding: 2rem 1rem;
            }
            
            .login-title {
                font-size: 2.5rem;
            }
            
            .students-image {
                width: 70%;
                max-height: 200px;
            }
            
            .slogan {
                font-size: 1.2rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 1rem;
            }
            
            .main-container {
                border-radius: 20px;
                margin: 0;
                max-width: 100%;
            }
            
            .left-section,
            .right-section {
                padding: 1.5rem;
            }
            
            .login-title {
                font-size: 2rem;
            }
        }

        /* Animaciones de entrada */
        .main-container {
            animation: slideUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            animation-fill-mode: both;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Efectos de loading */
        .loading {
            background-color: #9ca3af !important;
            cursor: not-allowed !important;
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Sección izquierda con imagen y shape -->
        <div class="left-section">
            <!-- SVG Shape Background -->
            <svg class="background-shape" viewBox="0 0 550 561" preserveAspectRatio="none">
                <path d="M0 25C0 11.1929 11.1929 0 25 0H521.935C543.409 0 554.89 25.2886 540.755 41.4552L354.384 254.62C346.365 263.792 346.125 277.41 353.818 286.858L543.797 520.216C557.095 536.55 545.472 561 524.41 561H25C11.1929 561 0 549.807 0 536V25Z" fill="#7752CC"/>
            </svg>
            
            <!-- Imagen de estudiantes -->
             <img src="img/login_students.png" class="students-image" alt="Estudiantes">


        </div>

        <!-- Sección derecha con formulario -->
        <div class="right-section">
            <div class="login-card">
                <h1 class="login-title">Inicia Sesión</h1>
                <p class="login-subtitle">Panel Administrativo</p>

                <?php if ($error): ?>
                    <div class="error-message">
                        🚫 <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Correo Electrónico"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <div class="password-container">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Contraseña"
                                class="password-input"
                                required
                            >
                            <button type="button" class="eye-button" onclick="togglePassword()">
                                <img src="img/open.png" alt="Show password" width="20">
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="login-btn" id="submitBtn">
                        Ingresar
                    </button>

                </form>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeButton = document.querySelector('.eye-button');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeButton.innerHTML = `<img src="img/close.png" alt="Hide password" width="20">`;
            } else {
                passwordInput.type = 'password';
                eyeButton.innerHTML = `<img src="img/open.png" alt="Show password" width="20">`;
            }
        }
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<span class="spinner"></span>Verificando...';
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            
            setTimeout(() => {
                if (submitBtn.classList.contains('loading')) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                }
            }, 3000);
        });

        document.querySelectorAll('input').forEach((input, index) => {
            input.addEventListener('focus', function() {
                this.style.transform = 'translateY(-1px)';
            });
            
            input.addEventListener('blur', function() {
                this.style.transform = 'translateY(0)';
            });

            input.addEventListener('input', function() {
                this.style.boxShadow = '0 0 15px rgba(217, 135, 186, 0.3)';
                setTimeout(() => {
                    this.style.boxShadow = '';
                }, 200);
            });
        });

        window.addEventListener('load', function() {
            document.querySelector('.main-container').style.animation = 'slideUp 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
        });
    </script>
</body>
</html>