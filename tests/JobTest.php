<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sJob;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\ResourcesList;

class JobTest extends TestCase
{
    public function test_job_build()
    {
        $pi = K8s::container()
            ->setName('pi')
            ->setImage('perl')
            ->setCommand(['perl',  '-Mbignum=bpi', '-wle', 'print bpi(2000)']);

        $pod = $this->cluster->pod()
            ->setName('perl')
            ->setContainers([$pi])
            ->restartOnFailure()
            ->neverRestart();

        $job = $this->cluster->job()
            ->setName('pi')
            ->setLabels(['tier' => 'backend'])
            ->setAnnotations(['perl/annotation' => 'yes'])
            ->setTTL(3600)
            ->setTemplate($pod);

        $this->assertEquals('batch/v1', $job->getApiVersion());
        $this->assertEquals('pi', $job->getName());
        $this->assertEquals(['tier' => 'backend'], $job->getLabels());
        $this->assertEquals(['perl/annotation' => 'yes'], $job->getAnnotations());
        $this->assertEquals($pod->getName(), $job->getTemplate()->getName());
        $this->assertEquals('Never', $pod->getRestartPolicy());

        $this->assertInstanceOf(K8sPod::class, $job->getTemplate());
    }

    public function test_job_from_yaml()
    {
        $pi = K8s::container()
            ->setName('pi')
            ->setImage('perl')
            ->setCommand(['perl',  '-Mbignum=bpi', '-wle', 'print bpi(2000)']);

        $pod = $this->cluster->pod()
            ->setName('perl')
            ->setContainers([$pi])
            ->restartOnFailure()
            ->neverRestart();

        $job = $this->cluster->fromYamlFile(__DIR__.'/yaml/job.yaml');

        $this->assertEquals('batch/v1', $job->getApiVersion());
        $this->assertEquals('pi', $job->getName());
        $this->assertEquals(['tier' => 'backend'], $job->getLabels());
        $this->assertEquals(['perl/annotation' => 'yes'], $job->getAnnotations());
        $this->assertEquals($pod->getName(), $job->getTemplate()->getName());
        $this->assertEquals('Never', $pod->getRestartPolicy());

        $this->assertInstanceOf(K8sPod::class, $job->getTemplate());
    }

    public function test_job_api_interaction()
    {
        $this->runCreationTests();
        $this->runGetAllTests();
        $this->runGetTests();
        $this->runUpdateTests();
        $this->runWatchAllTests();
        $this->runWatchTests();
        $this->runDeletionTests();
    }

    public function runCreationTests()
    {
        $pi = K8s::container()
            ->setName('pi')
            ->setImage('perl')
            ->setCommand(['perl',  '-Mbignum=bpi', '-wle', 'print bpi(2000)']);

        $pod = $this->cluster->pod()
            ->setName('perl')
            ->setContainers([$pi])
            ->neverRestart();

        $job = $this->cluster->job()
            ->setName('pi')
            ->setLabels(['tier' => 'backend'])
            ->setAnnotations(['perl/annotation' => 'yes'])
            ->setTTL(3600)
            ->setTemplate($pod);

        $this->assertFalse($job->isSynced());

        $job = $job->create();

        $this->assertTrue($job->isSynced());

        $this->assertInstanceOf(K8sJob::class, $job);

        $this->assertEquals('batch/v1', $job->getApiVersion());
        $this->assertEquals('pi', $job->getName());
        $this->assertEquals(['tier' => 'backend'], $job->getLabels());
        $this->assertEquals(['perl/annotation' => 'yes'], $job->getAnnotations());
        $this->assertEquals($pod->getName(), $job->getTemplate()->getName());

        $this->assertInstanceOf(K8sPod::class, $job->getTemplate());

        // Wait for the pod to create entirely.
        sleep(20);
    }

    public function runGetAllTests()
    {
        $jobs = $this->cluster->getAllJobs();

        $this->assertInstanceOf(ResourcesList::class, $jobs);

        foreach ($jobs as $job) {
            $this->assertInstanceOf(K8sJob::class, $job);

            $this->assertNotNull($job->getName());
        }
    }

    public function runGetTests()
    {
        $job = $this->cluster->getJobByName('pi');

        $this->assertInstanceOf(K8sJob::class, $job);

        $this->assertTrue($job->isSynced());

        $this->assertEquals('batch/v1', $job->getApiVersion());
        $this->assertEquals('pi', $job->getName());
        $this->assertEquals(['tier' => 'backend'], $job->getLabels());
        $this->assertEquals(['perl/annotation' => 'yes'], $job->getAnnotations());

        $this->assertInstanceOf(K8sPod::class, $job->getTemplate());
    }

    public function runUpdateTests()
    {
        $job = $this->cluster->getJobByName('pi');

        $this->assertTrue($job->isSynced());

        $job->setAnnotations([]);

        $this->assertTrue($job->update());

        $this->assertTrue($job->isSynced());

        $this->assertEquals('batch/v1', $job->getApiVersion());
        $this->assertEquals('pi', $job->getName());
        $this->assertEquals(['tier' => 'backend'], $job->getLabels());
        $this->assertEquals([], $job->getAnnotations());

        $this->assertInstanceOf(K8sPod::class, $job->getTemplate());
    }

    public function runDeletionTests()
    {
        $job = $this->cluster->getJobByName('pi');

        $this->assertTrue($job->delete());

        sleep(20);

        $this->expectException(KubernetesAPIException::class);

        $pod = $this->cluster->getJobByName('pi');
    }

    public function runWatchAllTests()
    {
        $watch = $this->cluster->job()->watchAll(function ($type, $job) {
            if ($job->getName() === 'pi') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests()
    {
        $watch = $this->cluster->job()->watchByName('pi', function ($type, $job) {
            return $job->getName() === 'pi';
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
