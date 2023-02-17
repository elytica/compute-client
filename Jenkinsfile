pipeline {
    agent any

    environment {
        COMPUTE_TOKEN = credentials('my-compute-token')
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Install dependencies') {
            steps {
                sh 'composer install --prefer-dist --no-interaction'
            }
        }

        stage('Run unit tests') {
            steps {
                withEnv(["COMPUTE_TOKEN=${COMPUTE_TOKEN}"]) {
                    sh 'vendor/bin/phpunit tests/'
                }
            }
        }

        stage('Generate version') {
            steps {
                sh 'composer global require conventional-commits-releaser'
                sh 'conventional-commits-releaser'
                env.NEW_VERSION = sh(script: 'cat .version', returnStdout: true).trim()
            }
        }

        stage('Upload to Packagist') {
            steps {
                withCredentials([string(credentialsId: 'packagist-username', variable: 'PACKAGIST_USERNAME'), string(credentialsId: 'packagist-apikey', variable: 'PACKAGIST_APIKEY')]) {
                    sh "composer config --global --auth http-basic.repo.packagist.com ${PACKAGIST_USERNAME} ${PACKAGIST_APIKEY}"
                    sh "composer version ${NEW_VERSION}"
                    sh 'composer upload'
                }
            }
        }
    }
}

