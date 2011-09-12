<?php
require '../src/facebook.php';

// REPLACE WITH YOUR OWN VALUE
$facebook = new Facebook(array(
  'appId'  => 'YOUR_APP_ID',
  'secret' => 'YOUR_APP_SECRET'
));

// application access token (required by some of the deprecated functions)
// note: there is a getApplicationAccessToken function, but it is protected
$appAccessToken = $facebook->getAppId() .'|'. $facebook->getApiSecret();

// application permissions
// http://developers.facebook.com/docs/reference/api/permissions/
$permissions = "email";

// if this page is being displayed in an iframe, ask facebook to get the page url
// to display the app, append sk=app_{APP ID} to the url
$signedrequest = $facebook->getSignedRequest();
if( is_array($signedrequest) && array_key_exists("page", $signedrequest) ){
	$facebookPageUrl = json_decode(file_get_contents("https://graph.facebook.com/" . $signedrequest['page']['id']))->{"link"} . "?sk=app_" . $facebook->getAppId();
} else {
	$facebookPageUrl = ((@$_SERVER["HTTPS"] == "on") ? "https://" : "http://") . $_SERVER["SERVER_NAME"] . ($_SERVER["SERVER_PORT"] != 80 ? ":" . $_SERVER["SERVER_PORT"] : "") . $_SERVER["REQUEST_URI"];
}

// determine if user has already been set
$user = $facebook->getUser();
if ($user) {
	try {
		// user is authenticated, get user information
		$user_profile = $facebook->api('/me');
		
		// Use the old rest api to get application properties (WARNING: these methods will eventually be deprecated)
		// http://developers.facebook.com/docs/appproperties/
		$facebookApplicationName = json_decode($facebook->api(array(
		     'method' => 'admin.getappproperties',
           'properties' => 'application_name',
			  'access_token'=>$appAccessToken
       )))->{"application_name"};
		
	} catch (FacebookApiException $e) {
		if( $e->getMessage() == "Error validating access token: The session is invalid because the user logged out."){
			echo '<pre>'.htmlspecialchars(print_r($e, true)).'</pre>';
		}
		$user = null;
	}
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Facebook SDK Example: PHP-SDK with JS-SDK</title>
		<script type="text/javascript" src="http://code.jquery.com/jquery-1.6.3.min.js"></script>
		<script type="text/javascript">
			$(function(){
				///////////////////////////
				// FACEBOOK JS-SDK INIT //
				/////////////////////////
				$('body').append('<div id="fb-root"></div>');
				$.getScript(document.location.protocol + '//connect.facebook.net/en_US/all.js');
				
				//////////////////////////////////////////
				// FACEBOOK SPECIFIC APPLICATION LOGIC //
				////////////////////////////////////////
				window.fbAsyncInit = function() {
					FB.init({
						appId: '<?php echo $facebook->getAppId() ?>',
						cookie: true,
						xfbml: true,
						oauth: true
					});
					
					// PHP SDK Login status
					phpLoginStatus = <?php echo ($user) ? 'true' : 'false' ?>;
					
					///////////////////////////
					// FACEBOOK EVENTS      //
					/////////////////////////
					FB.Event.subscribe('auth.login', function(response) {
						// only refresh the page if the server does not register the page as logged in
						if(!phpLoginStatus){
							if (window.location != window.parent.location) {
								// page is in iframe so we need to refresh it
								top.location.href = '<?php echo $facebookPageUrl ?>';
							} else {
								// page is displayed standalone
								window.location.reload();
							}
						}
						
						// any other logic you want to run any time a login event is fired
					});

					FB.Event.subscribe('auth.logout', function(response) {
						// only refresh the page if the server has the user marked as logged in
						if(phpLoginStatus){
							if (window.location != window.parent.location) {
								// page is in iframe so we need to refresh it
								top.location.href = '<?php echo $facebookPageUrl ?>';
							} else {
								// page is displayed standalone
								window.location.reload();
							}
						}
						
						// any other logic you want to run any time a logout even is fired
					});
	
					//////////////////////////////////////////
					// FACEBOOK SPECIFIC FUNCTIONALITY     //
					////////////////////////////////////////
					
					// Login
					$('#fbLoginButton').click(function(){
						FB.login(function(response){
							if(response.authResponse){
								console.log("user login successful");
							} else {
								console.log("user cancelled or did not fully authorize the app");
							}
						},
						{scope: "<?php echo $permissions ?>"}
						);
						
						return false;
					});
			  };
			
				///////////////////////////
				// OTHER LOGIC          //
				/////////////////////////
				
			});
		</script>
  <body>
    <?php if ($user) { ?>
      <p>You are logged in and have granted permission to the app.</p>
      <pre>
        <?php echo htmlspecialchars(print_r($user_profile, true)) ?>
      </pre>
		<p>Your application name is: <strong><?php echo $facebookApplicationName ?></strong></p>
		<p>Your application is being accessed at: <?php echo $facebookPageUrl ?></p>
    <?php } else { ?>
      <a id="fbLoginButton" href="#">Login to Facebook</a>
    <?php } ?>

  </body>
</html>