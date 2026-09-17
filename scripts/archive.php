<?php
/**
 * Development-only ZIP writer using PHP's built-in ZipArchive extension.
 *
 * @package MF\VeLog\Tooling
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

if ( ! class_exists( 'ZipArchive' ) || 4 !== $argc ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Standalone CLI tool; WordPress is not loaded.
	fwrite( STDERR, "Requires PHP ZipArchive and staging, output and slug arguments.\n" );
	exit( 1 );
}

$staging = realpath( $argv[1] );
$archive = $argv[2];
$slug    = $argv[3];

if ( false === $staging || ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug ) ) {
	exit( 1 );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $archive, ZipArchive::CREATE | ZipArchive::EXCL ) ) {
	throw new RuntimeException( 'Cannot create archive.' );
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $staging, FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
	if ( $file->isLink() || ! $file->isFile() ) {
		throw new RuntimeException( 'Only regular package files are allowed.' );
	}
	$relative = substr( $file->getPathname(), strlen( $staging ) + 1 );
	if ( ! $zip->addFile( $file->getPathname(), $slug . '/' . $relative ) ) {
		throw new RuntimeException( 'Cannot add package file.' );
	}
}
if ( ! $zip->close() ) {
	throw new RuntimeException( 'Cannot finalize archive.' );
}
$check = new ZipArchive();
// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive exposes this native property.
if ( true !== $check->open( $archive, ZipArchive::CHECKCONS ) || 0 === $check->numFiles ) {
	throw new RuntimeException( 'Archive validation failed.' );
}
$check->close();
