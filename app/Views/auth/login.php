<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EMKL Approval System</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --primary: #0ea5e9;
            --primary-hover: #0284c7;
            --error: #ef4444;
            --border: #e5e7eb;
            --ring: rgba(14, 165, 233, 0.3);
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-wrapper {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }

        .login-card {
            background-color: var(--card-bg);
            border-radius: 16px;
            padding: 2.5rem 2rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .login-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transform: translateY(-2px);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.025em;
        }

        .login-header p {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin: 0;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--text-main);
            background-color: #f9fafb;
            border: 1px solid var(--border);
            border-radius: 8px;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background-color: #fff;
            box-shadow: 0 0 0 3px var(--ring);
        }

        .btn-primary {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: #fff;
            background-color: var(--primary);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.1s ease;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-primary:active {
            transform: scale(0.98);
        }

        .alert-error {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--error);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: shake 0.4s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .sys-logo {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, #3b82f6 100%);
            border-radius: 12px;
            margin: 0 auto 1rem auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            box-shadow: 0 4px 6px -1px rgba(14, 165, 233, 0.3);
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="sys-logo">E</div>
                <h1>EMKL Approval</h1>
                <p>Silakan masuk ke akun Anda</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <?= esc($error) ?>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('login') ?>" method="POST">
                <?= csrf_field() ?>
                
                <div class="form-group">
                    <label class="form-label" for="pUser">Username</label>
                    <input type="text" id="pUser" name="pUser" class="form-control" placeholder="Masukkan Username" autocomplete="off" required autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="pPassword">Password</label>
                    <input type="password" id="pPassword" name="pPassword" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="form-group" style="margin-top: 2rem; margin-bottom: 0;">
                    <button type="submit" class="btn-primary">Masuk</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
