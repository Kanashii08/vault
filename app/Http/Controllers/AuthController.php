<?php

/**
 * AuthController
 * 
 * Handles user authentication: register, login, logout, and current user.
 */

class AuthController
{
    private function smtpRead($fp)
    {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) break;
            $data .= $line;
            if (preg_match('/^\d{3} /', $line)) break;
        }
        return $data;
    }

    private function smtpWrite($fp, $command)
    {
        fwrite($fp, $command . "\r\n");
        return $this->smtpRead($fp);
    }

    private function smtpExpectOk($response)
    {
        $code = (int) substr(trim($response), 0, 3);
        return $code >= 200 && $code < 400;
    }

    private function sendVerificationEmail($toEmail, $verifyUrl)
    {
        $configPath = __DIR__ . '/../../../config/mail.php';
        if (!file_exists($configPath)) {
            return false;
        }
        $mail = require $configPath;

        $host = (string) ($mail['host'] ?? '');
        $port = (int) ($mail['port'] ?? 587);
        $username = (string) ($mail['username'] ?? '');
        $password = (string) ($mail['password'] ?? '');
        $encryption = $mail['encryption'] ?? 'tls';
        $fromEmail = (string) ($mail['from_email'] ?? 'no-reply@bookcafe.local');
        $fromName = (string) ($mail['from_name'] ?? 'Book Cafe');

        if ($host === '' || $username === '' || $password === '' || $toEmail === '') {
            return false;
        }

        $scheme = null;
        if ($encryption === 'ssl') {
            $scheme = 'ssl://';
        }

        $fp = @fsockopen(($scheme ? $scheme : '') . $host, $port, $errno, $errstr, 15);
        if (!$fp) {
            return false;
        }

        stream_set_timeout($fp, 15);

        $greeting = $this->smtpRead($fp);
        if (!$this->smtpExpectOk($greeting)) {
            fclose($fp);
            return false;
        }

        $localHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $resp = $this->smtpWrite($fp, 'EHLO ' . $localHost);
        if (!$this->smtpExpectOk($resp)) {
            $resp = $this->smtpWrite($fp, 'HELO ' . $localHost);
            if (!$this->smtpExpectOk($resp)) {
                fclose($fp);
                return false;
            }
        }

        if ($encryption === 'tls') {
            $resp = $this->smtpWrite($fp, 'STARTTLS');
            if (!$this->smtpExpectOk($resp)) {
                fclose($fp);
                return false;
            }
            $cryptoOk = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoOk !== true) {
                fclose($fp);
                return false;
            }
            $resp = $this->smtpWrite($fp, 'EHLO ' . $localHost);
            if (!$this->smtpExpectOk($resp)) {
                fclose($fp);
                return false;
            }
        }

        $resp = $this->smtpWrite($fp, 'AUTH LOGIN');
        if (!$this->smtpExpectOk($resp) && strpos($resp, '334') !== 0) {
            fclose($fp);
            return false;
        }
        $resp = $this->smtpWrite($fp, base64_encode($username));
        if (strpos($resp, '334') !== 0) {
            fclose($fp);
            return false;
        }
        $resp = $this->smtpWrite($fp, base64_encode($password));
        if (!$this->smtpExpectOk($resp)) {
            fclose($fp);
            return false;
        }

        $resp = $this->smtpWrite($fp, 'MAIL FROM:<' . $fromEmail . '>');
        if (!$this->smtpExpectOk($resp)) {
            fclose($fp);
            return false;
        }

        $resp = $this->smtpWrite($fp, 'RCPT TO:<' . $toEmail . '>');
        if (!$this->smtpExpectOk($resp)) {
            fclose($fp);
            return false;
        }

        $resp = $this->smtpWrite($fp, 'DATA');
        if (strpos($resp, '354') !== 0) {
            fclose($fp);
            return false;
        }

        $subject = 'Verify your email - Book Cafe';
        $body = "Hello,\r\n\r\nPlease verify your email by clicking this link:\r\n\r\n{$verifyUrl}\r\n\r\nIf you did not create this account, you can ignore this email.\r\n";
        $headers = [];
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        $headers[] = 'To: <' . $toEmail . '>';
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $message = str_replace(["\r\n."], ["\r\n.."], $message);

        fwrite($fp, $message . "\r\n.\r\n");
        $resp = $this->smtpRead($fp);
        if (!$this->smtpExpectOk($resp)) {
            fclose($fp);
            return false;
        }

        $this->smtpWrite($fp, 'QUIT');
        fclose($fp);
        return true;
    }

    /**
     * Register a new user
     */
    public function register()
    {
        $data = validate([
            'first_name' => 'required',
            'last_name'  => 'required',
            'email'      => 'required|email',
            'password'   => 'required|min:6',
        ]);

        // Check if email already exists
        $stmt = db()->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => strtolower($data['email'])]);
        if ($stmt->fetch()) {
            response(['error' => true, 'message' => 'Email already registered'], 400);
        }

        $verifyToken = bin2hex(random_bytes(16));

        // Create user (store password as plain text for simplicity in this project)
        $stmt = db()->prepare('
            INSERT INTO users (first_name, last_name, email, password, role, avatar_url, email_verified_at, remember_token, created_at, updated_at)
            VALUES (:first_name, :last_name, :email, :password, :role, :avatar_url, NULL, :remember_token, NOW(), NOW())
        ');
        $stmt->execute([
            'first_name' => trim($data['first_name']),
            'last_name'  => trim($data['last_name']),
            'email'      => strtolower(trim($data['email'])),
            // NOTE: storing plain text password for demo/school purposes only
            'password'   => trim($data['password']),
            'role'       => 'user',
            'avatar_url' => 'https://moodleaands.muccs.site/backend/storage/avatars/default-user.png',
            'remember_token' => $verifyToken,
        ]);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $appConfigPath = __DIR__ . '/../../../config/app.php';
        $appConfig = file_exists($appConfigPath) ? (require $appConfigPath) : [];
        $frontendUrl = (string) ($appConfig['frontend_url'] ?? 'https://kanashii08.github.io/ITEL4ememe/frontend/');
        $verifyUrl = $scheme . '://' . $host . '/backend/api/auth/verify-email?email=' . urlencode(strtolower(trim($data['email']))) . '&token=' . urlencode($verifyToken) . '&redirect=' . urlencode($frontendUrl);

        $sent = $this->sendVerificationEmail(strtolower(trim($data['email'])), $verifyUrl);
        if (!$sent) {
            response([
                'error' => true,
                'message' => 'Account created but verification email could not be sent. Please contact support or try again later.',
                'verify_url' => $verifyUrl,
            ], 200);
        }

        response(['success' => true, 'message' => 'Registration successful. Please verify your email before logging in.']);
    }

    /**
     * Login user and return token
     */
    public function login()
    {
        $data = validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // Find user
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => strtolower($data['email'])]);
        $user = $stmt->fetch();

        // Compare plain text password (no hashing)
        if (!$user || $data['password'] !== $user['password']) {
            response(['error' => true, 'message' => 'Invalid credentials'], 401);
        }

        // Block login only for newly registered accounts that are still pending verification
        if (empty($user['email_verified_at']) && !empty($user['remember_token'])) {
            response(['error' => true, 'message' => 'Please verify your email before logging in'], 403);
        }

        // Generate token
        $plainToken = generateToken();
        $hashedToken = hash('sha256', $plainToken);

        // Store token
        $stmt = db()->prepare('
            INSERT INTO personal_access_tokens (tokenable_type, tokenable_id, name, token, abilities, created_at, updated_at)
            VALUES (:type, :id, :name, :token, :abilities, NOW(), NOW())
        ');
        $stmt->execute([
            'type'      => 'App\\Models\\User',
            'id'        => $user['id'],
            'name'      => 'bookcafe-token',
            'token'     => $hashedToken,
            'abilities' => '["*"]',
        ]);

        // Return user data (without password)
        $userData = [
            'id'         => (int) $user['id'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'avatar_url' => $user['avatar_url'],
        ];

        response([
            'token' => $plainToken,
            'user'  => $userData,
        ]);
    }

    public function verifyEmail()
    {
        $email = strtolower(trim($_GET['email'] ?? ''));
        $token = (string) ($_GET['token'] ?? '');
        $redirect = (string) ($_GET['redirect'] ?? '');

        if ($email) {
            if ($token !== '') {
                $update = db()->prepare('
                    UPDATE users
                    SET email_verified_at = NOW(), remember_token = NULL, updated_at = NOW()
                    WHERE email = :email AND remember_token = :token
                ');
                $update->execute(['email' => $email, 'token' => $token]);
            } else {
                // Legacy fallback (older verification links without token)
                $update = db()->prepare('
                    UPDATE users
                    SET email_verified_at = NOW(), updated_at = NOW()
                    WHERE email = :email AND email_verified_at IS NULL AND remember_token IS NULL
                ');
                $update->execute(['email' => $email]);
            }
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $appConfigPath = __DIR__ . '/../../../config/app.php';
        $appConfig = file_exists($appConfigPath) ? (require $appConfigPath) : [];
        $frontendUrl = (string) ($appConfig['frontend_url'] ?? 'https://kanashii08.github.io/ITEL4ememe/frontend/');
        $redirectBase = $redirect !== '' ? $redirect : $frontendUrl;
        $redirectUrl = rtrim($redirectBase, '/') . '/?verified=1';
        if ($email) {
            $redirectUrl .= '&email=' . urlencode($email);
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Logout user (revoke token)
     */
    public function logout()
    {
        $user = auth();
        if ($user && isset($user['id'])) {
            $stmt = db()->prepare('DELETE FROM personal_access_tokens WHERE id = :id');
            $stmt->execute(['id' => $user['id']]);
        }
        response(['success' => true, 'message' => 'Logged out']);
    }

    /**
     * Get current authenticated user
     */
    public function user()
    {
        $user = auth();
        response([
            'user' => [
                'id'         => (int) $user['user_id'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'avatar_url' => $user['avatar_url'],
            ]
        ]);
    }
}
