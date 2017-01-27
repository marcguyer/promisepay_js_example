<?php
// run the php built-in server wherever this script lives:
// $ php -S localhost:8000
// then go to http://localhost:8000/pp_js_test.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title></title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
    <!-- another required dependency -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.payment/1.4.1/jquery.payment.min.js"></script>

    <script src="https://js.prelive.promisepay.com/PromisePay.js" type="text/javascript"></script>

</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6 col-md-offset-3">

    <?php
    date_default_timezone_set('UTC');

    if (!empty($_GET['promisePayCardName'])) {
        ?>
        <div class="alert alert-success" role="alert">
            New Card Account created with ID=<?= $_GET['promisePayCardName'] ?>
        </div>
        <?php
    }
    ?>

    <?php
    if (!empty($_POST['username']) && !empty($_POST['apiKey'])) {
        $curl = curl_init('https://test.api.promisepay.com/token_auths');
        // verbose to output to stderr
        curl_setopt($curl, CURLOPT_VERBOSE, true);
        // include the headers so we can display it in case of error
        curl_setopt($curl, CURLOPT_HEADER, true);
        // basic auth
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC) ;
        curl_setopt(
            $curl,
            CURLOPT_USERPWD,
            "{$_POST['username']}:{$_POST['apiKey']}"
        );
        // post according to https://reference.promisepay.com/#token-auth
        curl_setopt(
            $curl,
            CURLOPT_POSTFIELDS,
            array(
                'token_type' => 'card',
                'user_id' => !empty($_POST['user_id']) ? $_POST['user_id'] : null
            )
        );
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        if ($errno = curl_errno($curl)) {
            $error_message = curl_strerror($errno);
            echo "<p>cURL error ({$errno}): {$error_message}</p>";
            curl_close($curl);
        } else {
            $info = curl_getinfo($curl);

            $headers = substr($response, 0, $info['header_size']);
            $body = trim(substr($response, $info['header_size']));

            switch ($info['http_code']) {
                case 200:  # OK
                    break;
                default:
                    echo "<p>Unexpected HTTP code: {$info['http_code']} </p>";
                    echo "<p><pre>$response</pre></p>";
            }
            if ($body) {
                $obj = json_decode($body);
                $auth_token = $obj->token_auth->token;
                $user_id = $obj->token_auth->user_id;
            }
        }
    }

    if (empty($auth_token)) {
        // get a new token server-side
        ?>
        <h1>Request Auth Token <small><code>POST /token_auths</code></small></h1>

        <p>
            This is done server-side with marketplace Username and API Key.
            Based on the
            <a href="https://docs.promisepay.com/feature-guides/guides/capturing-a-credit-card/">
                Capturing a Credit Card
            </a>
            guide.
        </p>

        <form autocomplete="on" method="POST"
            action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>">
            <div class="form-group">
                <input type="text" class="form-control"
                    value="<?= !empty($_POST['username']) ? $_POST['username'] : '' ?>"
                    placeholder="PromisePay Username"
                    name="username">
            </div>
            <div class="form-group">
                <input type="text" class="form-control"
                    value="<?= !empty($_POST['apiKey']) ? $_POST['apiKey'] : '' ?>"
                    placeholder="PromisePay API Key"
                    name="apiKey">
            </div>
            <div class="form-group">
                <input type="text" class="form-control"
                    value="<?= !empty($_POST['user_id']) ? $_POST['user_id'] : '' ?>"
                    placeholder="Optional PromisePay user_id"
                    name="user_id">
            </div>
            <button type="submit" class="btn btn-default">
                Get Auth Token
            </button>
        </form>
        <?php
    } else {
        ?>
        <div class="alert alert-success" role="alert">
            Auth Token: <code><?= $auth_token ?></code><br/>
            Requested: <code><?= date('c') ?></code><br/>
            <small>Refresh to rerun auth token request</small>
        </div>

        <h1>Record a new Card Account</h1>

        <p>
            This is done client-side using the
            <code>auth_token</code> for authentication.
            Full logging may be found in the browser console log.
            Based on the
            <a href="https://docs.promisepay.com/feature-guides/guides/integrating-promisepay-js/">
                Integrating PromisePay.js
            </a>
            guide. Ultimately the PromisePay.js lib calls
            <a href="https://reference.promisepay.com/#create-card-account">
                Create a Card Account
            </a>
            with an ajax POST.
        </p>

        <script>

        $(document).ready(function() {
            promisepay.configure("prelive");

            // Create your callback methods
            var success = function(data) {
                console.log(data);
            }

            var fail = function(data) {
                console.log(data);
            }

            // Will return the Device ID as a string.
            var deviceId = promisepay.captureDeviceId(success, fail);

            // Will return the IP Address as a string.
            var ipAddress = promisepay.getIPAddress(success, fail);

            // handler for the "convenient" form method
            promisepay.createCardAccountForm(
                'promisepay-form',
                success,
                fail
            );

            // handler for doing it the old fashioned way
            $('#custom-form').submit(function(event) {
                event.preventDefault();

                $('#server-error').hide();

                var obj = {};
                $(this).serializeArray().map(function(item) {
                    obj[item.name] = item.value;
                });
                console.log('to be posted: ', obj);

                promisepay.createCardAccount(
                    '<?= $auth_token ?>',
                    obj,
                    function(data) {
                        console.log('success:', data);
                    },
                    function(data) {
                        console.log('fail:', data);
                        // if there's an errors object
                        $('#server-error').html(
                            '<pre>' +
                                JSON.stringify(data.responseJSON, null, 4) +
                                '</pre>'
                        ).show();
                    }
                );

            });

        });

        </script>

        <h2>Using <small><code>promisepay.createCardAccountForm()</code></small></h2>

        <p>
            This method includes some magic box data manipulation
        </p>

        <form id="promisepay-form" autocomplete="on">
            <input type="hidden" class="form-control"
                data-promisepay-card-token="<?php echo $auth_token ?>">
            <div class="form-group">
                <label>Cardholder Name</label>
                <input type="text" class="form-control"
                    placeholder="Full Name"
                    value="Bella Buyer"
                    data-promisepay-encrypted-name="cardName">
            </div>
            <div class="form-group">
                <label>Card Number</label>
                <input type="text" class="form-control"
                    placeholder="Card Number"
                    value="4111111111111111"
                    data-promisepay-encrypted-name="cardNumber">
            </div>
            <div class="form-group">
                <label>Card Expiry (MM/YYYY)</label>
                <input type="text" class="form-control"
                    placeholder="Card Expiry (MM/YYYY)"
                    value="<?= date('m/Y', strtotime('+1 year')) ?>"
                    data-promisepay-encrypted-name="cardExpiryDate">
            </div>
            <div class="form-group">
                <label>CVV</label>
                <input type="text" class="form-control"
                    autocomplete="off"
                    value="123"
                    data-promisepay-encrypted-name="cardCVC">
            </div>
            <p class="promisepay-server-error" style="display:none"></p>

            <input type="submit" class="btn btn-default">
        </form>

        <hr/>

        <h2>Using <small><code>promisepay.createCardAccount()</code></small></h2>

        <p>
            This method uses custom js code via the form submit event
            to compose a json object of the required data. Additional
            validation and error handling is recommended for a production
            system. This is here to test raw requests with minimal interference.
        </p>

        <form id="custom-form" autocomplete="on">
            <div class="form-group">
                <label>Cardholder Name</label>
                <input type="text" class="form-control"
                    placeholder="Full Name"
                    value="Bella Buyer"
                    name="full_name">
            </div>
            <div class="form-group">
                <label>Card Number</label>
                <input type="text" class="form-control"
                    placeholder="Card Number"
                    value="4111111111111111"
                    name="number">
            </div>
            <div class="form-group form-inline">
                <label>Card Expiry</label><br/>
                <div class="input-group">
                    <div class="input-group-addon">Month (MM)</div>
                    <input type="text" class="form-control"
                        placeholder="Card Expiry Month (MM)"
                        value="<?= date('m', strtotime('+1 year')) ?>"
                        name="expiry_month">
                </div>
                <div class="input-group">
                    <div class="input-group-addon">Year (YYYY)</div>
                    <input type="text" class="form-control"
                        placeholder="Card Expiry Year (YYYY)"
                        value="<?= date('Y', strtotime('+1 year')) ?>"
                        name="expiry_year">
                </div>
            </div>
            <div class="form-group">
                <label>CVV</label>
                <input type="text" class="form-control"
                    autocomplete="off"
                    value="123"
                    name="cvv">
            </div>
            <p id="server-error" style="display:none"></p>

            <input type="submit" class="btn btn-default">

        </form>
        <?php
    }
    ?>
        </div>
    </div>
</div>

</body>
</html>
