<!DOCTYPE html>
<html>
<head>
    <title>Connection Test</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .test { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; }
        .success { border-left: 4px solid #5cb85c; }
        .error { border-left: 4px solid #d9534f; }
        h2 { margin-top: 0; }
        pre { background: #f9f9f9; padding: 10px; overflow: auto; }
    </style>
</head>
<body>
    <h1>🔍 ReclaNova Connection Test</h1>

    <!-- TEST 1: PHP Version -->
    <div class="test success">
        <h2>✅ Test 1: PHP Version</h2>
        <p>PHP Version: <strong><?php echo phpversion(); ?></strong></p>
        <p>Required: PHP 7.0 or higher</p>
    </div>

    <!-- TEST 2: Database Connection -->
    <?php
    try {
        require_once 'db_config.php';
        echo '<div class="test success">';
        echo '<h2>✅ Test 2: Database Connection</h2>';
        echo '<p>Database connected successfully!</p>';
        echo '<p>Database: <strong>' . DB_NAME . '</strong></p>';
        
        // Test query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        echo '<p>Total users in database: <strong>' . $result['count'] . '</strong></p>';
        echo '</div>';
    } catch (Exception $e) {
        echo '<div class="test error">';
        echo '<h2>❌ Test 2: Database Connection FAILED</h2>';
        echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>Fix:</strong> Check your database credentials in db_config.php</p>';
        echo '</div>';
    }
    ?>

    <!-- TEST 3: Check Users Table -->
    <?php
    if (isset($pdo)) {
        try {
            $stmt = $pdo->query("SELECT id, nom, email, role FROM users LIMIT 3");
            $users = $stmt->fetchAll();
            
            echo '<div class="test success">';
            echo '<h2>✅ Test 3: Users Table</h2>';
            echo '<p>Found ' . count($users) . ' test users:</p>';
            echo '<pre>' . print_r($users, true) . '</pre>';
            echo '</div>';
        } catch (Exception $e) {
            echo '<div class="test error">';
            echo '<h2>❌ Test 3: Users Table FAILED</h2>';
            echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
    }
    ?>

    <!-- TEST 4: Password Hash Test -->
    <?php
    if (isset($pdo)) {
        try {
            $testPassword = 'password123';
            $stmt = $pdo->query("SELECT email, mot_de_passe FROM users WHERE email = 'jean.dupont@example.com'");
            $user = $stmt->fetch();
            
            if ($user) {
                $passwordWorks = password_verify($testPassword, $user['mot_de_passe']);
                
                if ($passwordWorks) {
                    echo '<div class="test success">';
                    echo '<h2>✅ Test 4: Password Verification</h2>';
                    echo '<p>Password <strong>password123</strong> works for <strong>' . $user['email'] . '</strong></p>';
                    echo '</div>';
                } else {
                    echo '<div class="test error">';
                    echo '<h2>❌ Test 4: Password Verification FAILED</h2>';
                    echo '<p>Password does not match. Run this SQL to fix:</p>';
                    echo '<pre>UPDATE users SET mot_de_passe = "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi" WHERE email = "jean.dupont@example.com";</pre>';
                    echo '</div>';
                }
            } else {
                echo '<div class="test error">';
                echo '<h2>❌ Test 4: User Not Found</h2>';
                echo '<p>jean.dupont@example.com not found in database</p>';
                echo '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="test error">';
            echo '<h2>❌ Test 4: Error</h2>';
            echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
    }
    ?>

    <!-- TEST 5: Test Auth Handler -->
    <?php
    if (isset($pdo)) {
        echo '<div class="test success">';
        echo '<h2>✅ Test 5: Test Login via JavaScript</h2>';
        echo '<button onclick="testLogin()" style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;">Test Login</button>';
        echo '<div id="loginResult" style="margin-top: 10px;"></div>';
        echo '</div>';
    }
    ?>

    <script>
    async function testLogin() {
        const resultDiv = document.getElementById('loginResult');
        resultDiv.innerHTML = '<p>Testing login...</p>';
        
        try {
            const response = await fetch('auth_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'login',
                    identifier: 'jean.dupont@example.com',
                    password: 'password123'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                resultDiv.innerHTML = '<p style="color: green;">✅ Login successful! Message: ' + data.message + '</p>';
            } else {
                resultDiv.innerHTML = '<p style="color: red;">❌ Login failed! Message: ' + data.message + '</p>';
            }
        } catch (error) {
            resultDiv.innerHTML = '<p style="color: red;">❌ Error: ' + error.message + '</p>';
        }
    }
    </script>

    <hr>
    <h3>📝 Next Steps:</h3>
    <ol>
        <li>Make sure all tests above show ✅ (green checkmarks)</li>
        <li>If any test fails, follow the fix instructions</li>
        <li>Check browser console (F12) for JavaScript errors</li>
        <li>Check error.log file in your project folder</li>
    </ol>

</body>
</html>