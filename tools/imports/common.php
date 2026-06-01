<?php

/**
 * Common to all tools
 */

ini_set("display_errors", true);
ini_set("error_reporting", E_ALL);

require __DIR__ . "/../../vendor/autoload.php";

// query ID format
const ID_FORMAT = '[a-zA-Z0-9]{8}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{12}';

// output location for query results
const QUERY_OUTPUT_LOCATION = "s3://%s/";

// introduction to usage details
const USAGE_INTRO = "What it does:\n\n    %s\n\nUsage: php %s %s\n";

/**
 * Read custom env file argument from CLI args
 *
 * Supported formats:
 * -f path/to/.env
 * --env-file path/to/.env
 * --env-file=path/to/.env
 *
 * @param array $argv command line arguments
 * @return array{0:?string,1:bool} [envFilePath, customFlag]
 */
function getEnvFileArgument(array $argv): array
{
    $envFilePath = null;
    $custom = false;

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        if ($arg === '-f' || $arg === '--env-file') {
            $custom = true;
            if (!isset($argv[$i + 1]) || strpos($argv[$i + 1], '-') === 0) {
                throw new \InvalidArgumentException("Missing path after option $arg.");
            }
            $envFilePath = $argv[$i + 1];
            $i++;
            continue;
        }

        if (strpos($arg, '--env-file=') === 0) {
            $custom = true;
            $envFilePath = substr($arg, strlen('--env-file='));
            if ($envFilePath === false || trim($envFilePath) === '') {
                throw new \InvalidArgumentException("Missing path after option --env-file.");
            }
        }
    }

    return [$envFilePath, $custom];
}

$envFilePath = __DIR__ . "/../..";

try {
    [$envFileArgPath, $customEnvFileProvided] = getEnvFileArgument($_SERVER['argv'] ?? []);
} catch (\InvalidArgumentException $e) {
    echo "Failed to load configuration variables from file: " . $e->getMessage();
    exit(1);
}

$envFile = '.env';
$envFileDirectory = $envFilePath;

if (!is_null($envFileArgPath)) {
    $envFileArgPath = trim($envFileArgPath);

    if ($envFileArgPath === '') {
        echo "Failed to load configuration variables from file: Empty path provided for --env-file.";
        exit(1);
    }

    if (is_readable($envFileArgPath)) {
        $resolvedPath = realpath($envFileArgPath);
        if ($resolvedPath === false) {
            echo "Failed to load configuration variables from file: Could not resolve env file path '$envFileArgPath'.";
            exit(1);
        }
        $envFileDirectory = dirname($resolvedPath);
        $envFile = basename($resolvedPath);
    } else {
        $candidatePath = "$envFilePath/$envFileArgPath";
        if (is_readable($candidatePath)) {
            $resolvedPath = realpath($candidatePath);
            if ($resolvedPath === false) {
                echo "Failed to load configuration variables from file: Could not resolve env file path '$candidatePath'.";
                exit(1);
            }
            $envFileDirectory = dirname($resolvedPath);
            $envFile = basename($resolvedPath);
        } else {
            echo "Failed to load configuration variables from file: Custom env file not found or not readable at '$envFileArgPath'.";
            exit(1);
        }
    }
}

try {
    if (!is_readable("$envFileDirectory/$envFile")) {
        if ($customEnvFileProvided) {
            throw new \Exception("Custom env file not found or not readable.");
        }
        throw new \Exception("Configuration variable file not found or not readable! Expecting a .env file under directory $envFilePath.");
    }

    if (count(\Dotenv\Dotenv::createImmutable($envFileDirectory, $envFile)->load()) === 0) {
        throw new \Exception("Make sure they were not previously loaded.");
    }

    define('DEFAULT_PROFILE', $_ENV['PROFILE']);
    define('DEFAULT_VERSION', $_ENV['VERSION']);
    define('DEFAULT_REGION', $_ENV['REGION']);
    define('DEFAULT_CATALOG', $_ENV['CATALOG']);
    define('DEFAULT_WORKGROUP', $_ENV['WORKGROUP']);
    define('DEFAULT_QUERY_OUTPUT', $_ENV['QUERY_OUTPUT']);

    define('DEFAULT_AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL1QUERIES', $_ENV['AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL1QUERIES']);
    define('DEFAULT_AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL1QUERIES', $_ENV['AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL1QUERIES']);
    define('DEFAULT_AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL2QUERIES', $_ENV['AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL2QUERIES']);
    define('DEFAULT_AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL2QUERIES', $_ENV['AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL2QUERIES']);
    define('DEFAULT_AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL3QUERIES', $_ENV['AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL3QUERIES']);
    define('DEFAULT_AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL3QUERIES', $_ENV['AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL3QUERIES']);
    define('DEFAULT_AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL4QUERIES', $_ENV['AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL4QUERIES']);
    define('DEFAULT_AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL4QUERIES', $_ENV['AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL4QUERIES']);
    define('DEFAULT_AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL5QUERIES', $_ENV['AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL5QUERIES']);
    define('DEFAULT_AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL5QUERIES', $_ENV['AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL5QUERIES']);
    define('DEFAULT_AWS_DEFAULT_SIMULTANEOUS_DDL_QUERIES', $_ENV['AWS_DEFAULT_SIMULTANEOUS_DDL_QUERIES']);
    define('DEFAULT_AWS_DEFAULT_SIMULTANEOUS_DML_QUERIES', $_ENV['AWS_DEFAULT_SIMULTANEOUS_DML_QUERIES']);

} catch (\Exception $e) {
    echo "Failed to load configuration variables from file: " . $e->getMessage();
    exit(1);
}

/**
 * Set options and display usage
 *
 * @param string $shortOpts list of short options
 * @param array $longOpts array of long options
 * @param string $usage usage details to display
 * @return array options
 */
function setOptions(string $shortOpts, array $longOpts, string $usage): array
{
    $options = getopt($shortOpts, $longOpts);
    if (isset($options['h']) || isset($options['help'])) {
        print $usage;
        print "\nGlobal options:\n";
        print "    -f/--env-file=path_to_env_file       [OPTIONAL] Path to custom .env file\n";
        print "                                               Default value: .env in project root\n";
        exit;
    }

    return $options;
}

/**
 * Initialize AWS configuration options
 *
 * @param array $options
 * @return void
 */
function initAwsConfigOptions(array $options): void
{
    define('OPTION_PROFILE', array_key_exists('p', $options) ? 'p' : 'profile');
    define('OPTION_VERSION', array_key_exists('v', $options) ? 'v' : 'version');
    define('OPTION_REGION', array_key_exists('r', $options) ? 'r' : 'region');
}

/**
 * Check whether a profile in ~/.aws/config uses credential_process
 *
 * @param string $profile AWS profile name
 * @return bool true if credential_process is configured, false otherwise
 */
function profileUsesCredentialProcess(string $profile): bool
{
    $configPath = getenv('AWS_CONFIG_FILE');
    if ($configPath === false || $configPath === '') {
        $home = getenv('HOME');
        $configPath = ($home === false || $home === '' ? '' : rtrim($home, '/')) . '/.aws/config';
    }

    if (!is_readable($configPath)) {
        return false;
    }

    $ini = parse_ini_file($configPath, true, INI_SCANNER_RAW);
    if (!is_array($ini)) {
        return false;
    }

    $section = 'profile ' . $profile;
    return isset($ini[$section]['credential_process']) && trim((string)$ini[$section]['credential_process']) !== '';
}

/**
 * Export temporary credentials for a profile via AWS CLI
 *
 * @param string $profile AWS profile name
 * @return array|null credentials array for AWS SDK, or null when unavailable
 */
function exportProfileCredentials(string $profile): ?array
{
    $cmd = sprintf('aws configure export-credentials --profile %s --format process 2>/dev/null', escapeshellarg($profile));
    $out = shell_exec($cmd);
    if (!is_string($out) || trim($out) === '') {
        return null;
    }

    $json = json_decode($out, true);
    if (!is_array($json) || !isset($json['AccessKeyId']) || !isset($json['SecretAccessKey'])) {
        return null;
    }

    $credentials = [
        'key' => $json['AccessKeyId'],
        'secret' => $json['SecretAccessKey']
    ];

    if (isset($json['SessionToken']) && $json['SessionToken'] !== '') {
        $credentials['token'] = $json['SessionToken'];
    }

    return $credentials;
}

/**
 * Initialize AWS configuration
 *
 * @param array $options
 * @return array associative array with profile, version and region initialized according to provided options
 */
function getAwsConfig(array $options): array
{
    $profile = $options[OPTION_PROFILE] ?? '';
    $profile = $profile !== '' ? $profile : DEFAULT_PROFILE;

    $version = $options[OPTION_VERSION] ?? '';
    $region = $options[OPTION_REGION] ?? '';

    $config = [
        'version' => $version !== '' ? $version : DEFAULT_VERSION,
        'region' => $region !== '' ? $region : DEFAULT_REGION
    ];

    if ($profile !== '') {
        // The PHP SDK does not resolve config-only credential_process profiles consistently.
        // When such a profile is detected, inject temporary credentials exported by AWS CLI.
        if (profileUsesCredentialProcess($profile)) {
            $exportedCredentials = exportProfileCredentials($profile);
            if (!is_null($exportedCredentials)) {
                $config['credentials'] = $exportedCredentials;
                return $config;
            }
        }

        $config['profile'] = $profile;
    }

    return $config;
}

function instantiateAthena(\Aws\Athena\AthenaClient $athenaClient, ?int $maxDDL = null, ?int $maxDML = null): \FC\AWS\Athena
{
    return new \FC\AWS\Athena(
        $athenaClient,
        DEFAULT_AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL1QUERIES,
        DEFAULT_AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL2QUERIES,
        DEFAULT_AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL3QUERIES,
        DEFAULT_AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL4QUERIES,
        DEFAULT_AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL5QUERIES,
        DEFAULT_AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL1QUERIES,
        DEFAULT_AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL2QUERIES,
        DEFAULT_AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL3QUERIES,
        DEFAULT_AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL4QUERIES,
        DEFAULT_AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL5QUERIES,
        $maxDDL ?? DEFAULT_AWS_DEFAULT_SIMULTANEOUS_DDL_QUERIES,
        $maxDML ?? DEFAULT_AWS_DEFAULT_SIMULTANEOUS_DML_QUERIES
    );
}

/**
 * Date&Time validator for all formats
 *
 * @param mixed $datetime date&time, date, time (full or partial), can be integer or string
 * @param string $format date/time format
 * @return bool true if validated, false otherwise
 */
function validateDateTime(mixed $datetime, string $format = 'Y-m-d H:i:s'): bool
{
    if (!is_scalar($datetime)) {
        return false;
    }

    $value = (string)$datetime;
    $d = \DateTime::createFromFormat($format, $value);

    return $d !== false && $d->format($format) === $value;
}

