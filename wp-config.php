<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'seanscoot' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'mq-qlY7!LazZ *EwPgT2cA{T25?CgA}n&A:YQ.Vu:bE2Mscz(hk[W):C|PnsnHZB' );
define( 'SECURE_AUTH_KEY',  '1@Z{0hoFAm:N+y8wCvX}?L9#LsJ~BtqYp4[s#VMA]`_ajY@;C~fm/?q#pzwGt=i/' );
define( 'LOGGED_IN_KEY',    'qP/jsVz*3QM#/@Zj]qw~;t 7<fi]v6}$VO6s3ncC-icjRW,tD=O:! 3|bG{smwTq' );
define( 'NONCE_KEY',        '>$iAB0u.5hZL ;QE<(@CMPFHwF`C3TrmVvYCZxwnnel2O6a~ gx$IZf42*WUj fG' );
define( 'AUTH_SALT',        'ENJeNgok_t8x;! i4St?!R]Uo7%+j6ip26Sm8x>}!T5363U8~5YVUCr*_/h?[_si' );
define( 'SECURE_AUTH_SALT', ',0H.x}(8l};5~g$DP#|5v]v_N&DYqPG(q_8nj)&Mj-Dse?lD1?hic=</71p2qJ/v' );
define( 'LOGGED_IN_SALT',   'LtB31=ALC+p$4L0nOTql=KNdmwy4m_zl|!zE8 >?1&%y$wH+u !?7I93uOCd[NLl' );
define( 'NONCE_SALT',       'MBtWB=)x*6oc}i;wAw((c&Ma2lLEp&z<}BR~,C3ng4pb+mK5(`;o8(/av rPT;*~' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
