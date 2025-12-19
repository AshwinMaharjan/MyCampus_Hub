<?php include("connect.php") ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forget Password</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="icon" href="Prime-College-Logo.ico" type="image/x-icon">


</head>
<body>
    <?php include("header.php") ?>

    <div class="login-container">
    <form class="login-form" method="POST" action="login.php">
        <h2>Forget Password Form</h2>
        <input type="email" name="email" placeholder="Email" required>

        <div class="login-options">
            <label><input type="checkbox" name="remember"> Remember me</label>
            <a href="forget_password.php">Forget Password?</a>
        </div>

        <button type="submit">Login</button>
        <p>Don't have an account? <a href="register.php" class="register-link">Register Now</a></p>
    </form>
</div>



    <?php include("footer.php") ?>
    <?php include("lower_footer.php") ?>

</body>
</html>