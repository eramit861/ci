<html>

<head>
    <title>My Form</title>
</head>

<body>

    <?php
    if (validation_errors()) {

        echo "<div class='error'>";
        echo validation_errors();
        echo "</div>";
    } ?>

    <?php echo form_open('/guest/get-form'); ?>

    <h5>Username</h5>
    <input type="text" name="user_name" value="" size="50" />

    <h5>Password</h5>
    <input type="text" name="user_password" value="" size="50" />

    <h5>Password Confirm</h5>
    <input type="text" name="passconf" value="" size="50" />

    <h5>Email Address</h5>
    <input type="text" name="user_email" value="" size="50" />

    <div><input type="submit" value="Submit" /></div>

    </form>

</body>

</html>