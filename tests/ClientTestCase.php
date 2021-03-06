<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:26
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;

class ClientTestCase extends \PHPUnit_Framework_TestCase
{

    const PRODUCTION_HOSTS = [
        'connection.keboola.com',
        'connection.eu-central-1.keboola.com',
    ];

    /**
     * Prefix of all maintainers created by tests
     */
    const TESTS_MAINTAINER_PREFIX = 'KBC_MANAGE_TESTS';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Client
     */
    protected $normalUserClient;

    protected $testMaintainerId;

    protected $normalUser;

    protected $superAdmin;

    public static function setUpBeforeClass()
    {
        $manageApiUrl = getenv('KBC_MANAGE_API_URL');

        if (in_array(parse_url($manageApiUrl, PHP_URL_HOST), self::PRODUCTION_HOSTS)) {
            throw new \Exception('Tests cannot be executed against production host - ' . $manageApiUrl);
        }

        // cleanup organizations and projects created in testing maintainer
        $client = new Client([
            'token' => getenv('KBC_MANAGE_API_TOKEN'),
            'url' => $manageApiUrl,
            'backoffMaxTries' => 0,
        ]);
        $organizations = $client->listMaintainerOrganizations(getenv('KBC_TEST_MAINTAINER_ID'));
        foreach ($organizations as $organization) {
            foreach ($client->listOrganizationProjects($organization['id']) as $project) {
                $client->deleteProject($project['id']);
            }
            $client->deleteOrganization($organization['id']);
        }
    }

    public function setUp()
    {
        $this->client = new Client([
            'token' => getenv('KBC_MANAGE_API_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 0,
        ]);
        $this->normalUserClient = new \Keboola\ManageApi\Client([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 0,
        ]);
        $this->testMaintainerId = getenv('KBC_TEST_MAINTAINER_ID');

        $this->normalUser = $this->normalUserClient->verifyToken()['user'];
        $this->superAdmin = $this->client->verifyToken()['user'];

        // cleanup maintainers created by tests
        $maintainers = $this->client->listMaintainers();
        foreach ($maintainers as $maintainer) {
            if ((int) $maintainer['id'] === (int) $this->testMaintainerId) {
                $members = $this->client->listMaintainerMembers($maintainer['id']);
                foreach ($members as $member) {
                    if ($member['id'] != $this->superAdmin['id']) {
                        $this->client->removeUserFromMaintainer($maintainer['id'], $member['id']);
                    }
                }
            } elseif (strpos($maintainer['name'], self::TESTS_MAINTAINER_PREFIX) === 0) {
                $this->client->deleteMaintainer($maintainer['id']);
            }
        }
    }

    public function getRandomFeatureSuffix()
    {
        return uniqid('', true);
    }
}
