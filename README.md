# yap-fbmessenger-bot

**Your instance will have to have HTTPS/SSL enabled.  You will also need redis running locally.**

1) Create a new app under your Facebook developer account https://developers.facebook.com/apps/.  Whatever you name it will be the name of your bot.  (Example: North Carolina Region of NA)
2) Select "Messenger" as application product
3) You will need to link your bot to a page.  Either use an existing one or make a new one.  It may make sense to your service bodies one (not your personal one).
4) A token will be generated which you will need to add to config.php as the following:

```php
static $fbmessenger_accesstoken = '';
```

5) The verify token is an arbitrary secret which you use to verify that the request is coming from Facebook.  It prevents someone from hijacking your endpoint and flooding with messages.  Set the value you pick to the value in config.php:

```php
static $fbmessenger_verifytoken = '';
```

6) Add a new webhook.
7) The callback URL will be your Yap server pointing to the file `fbmessenger-gateway.php`.
8) Select "messages" and "messaging_postbacks" under subscription fields.
9) Once you've created your webhook, assign it to the page you created.
10) Call `https://your-yap-server/fbmessenger-activate.php` to activate yap connection to Facebook.
11) By default you will be in development mode and you should be able to search for the bot under your messenger on your personal account.  Once you are satisfied you can turn on the bot, which will allow other people to find it.
12) When are ready to go out of development mode, you will need to set a Privacy Policy under Basic Settings.  You will also have to set a Category.
13) You will need to submit your app to Facebook for review.  This requires setting a logo, as well as some same submissions that they the Facebook team can test with.  It may take up to 5 days for the review to pass.

Other settings in config.php that will have to be set

`$yap_api_endpoint`: points to your Yap instance
`$google_maps_api_key`: your google maps api key (do not use referrer restrictions)
`$title`: intro message when someone starts a conversation with your bot
`$location_lookup_bias` (optional): set a bias on the google maps api lookup
`$result_count_max` (optional): set the max results to return on a lookup (default is 10).

Note: If you decide to change the `$title` in your config.php, you will have to force a refresh on your Facebook Messenger settings by calling `http://your-yap-server/fbmessenger-activate.php` again.  After this is done, it may take some time for Facebook to show these changes.
