WP Amazon SES and SNS is a WordPress plugin for sending emails via Amazon SES and tracking the emails with Amazon SNS. With WP Amazon SES and SNS, you can:

- View a list of all emails sent from your website.
- Track all emails sent from your website.
- You can track deliveries, opens, clicks, bounces, complaints, rejects, and rendering failures.
- View statistics of your email performance.
- View your Amazon SES daily sending quota.
- View your Amazon SES maximum send rate.
- Bulk delete invalid email addresses.
- Bulk delete users with invalid email addresses.
- View detailed log of all tracking events with timestamp.


# Getting started

To get started, you need to create an [AWS account](https://portal.aws.amazon.com/billing/signup#/) and verify your sending domain for SES.

Download and install the plugin on your WordPress website and add the following constants to `wp-config.php` file:

```PHP
define( 'AWS_SES_WP_MAIL_KEY', 'YOUR ACCESS KEY' );
define( 'AWS_SES_WP_MAIL_SECRET', 'YOUR SECRET KEY' );
define( 'AWS_SES_WP_MAIL_REGION', 'YOUR REGION' ); // E.g. eu-north-1
```

See how you can generate your [access & secret key](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

**Note:** If you have not used SES in production previously, you need to apply to [move out of the Amazon SES sandbox](http://docs.aws.amazon.com/ses/latest/DeveloperGuide/request-production-access.html).

Amazon SES has a cap on the number of emails you can send per second. If you intend to send bulk emails, add the following constants to `wp-config.php` file to prevent sending error. The plugin will limit the number of emails you can send per second based on your amazon maximum send rate.

```PHP
define( 'AWS_SES_WP_SEND_BULK_EMAIL', true ); // Optional.
```

## Tracking your emails

Add the following constants to `wp-config.php` file:

```PHP
define( 'AWS_SES_WP_MAIL_CONFIG', 'YOUR CONFIGURATION SET' );
```

Follow this [instruction](https://docs.aws.amazon.com/ses/latest/dg/creating-configuration-sets.html) to create a configuration set for tracking your emails. You also need to set up [Amazon SNS with HTTP/HTTPS protocol](https://docs.aws.amazon.com/sns/latest/dg/SendMessageToHttp.subscribe.html) to work with SES.

This is your HTTP/HTTPS endpoint for tracking - https://example.com/wp-json/amazon-sns/v1/email-tracking. Remember to replace example.com with your WordPress site domain.

Happy sending and tracking!


## Filters you can use to customize the plugin

aws_ses_wp_admin_list_statistics - To change statistics shown on the WordPress Admin List Table.

aws_ses_wp_admin_menu_position - To change the position of the Plugin menu link in admin dashboard.


# Credits

Created by [Sadiq Odunsi](https://sadiqodunsi.com).

Do you need help with the plugin or require customizations? Get in touch with me [Sadiq Odunsi](https://sadiqodunsi.com)

