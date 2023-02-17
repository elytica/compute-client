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
                script {
                    sh 'which standard-version'
                    if (env.EXIT_STATUS != 0) {
                        sh 'sudo npm install -g standard-version'
                    }
                }
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
                script {
                    def newVersion = sh(script: 'standard-version --dry-run | tee .version', returnStdout: true).trim().replaceAll(/^v/, '')
                    env.NEW_VERSION = newVersion
                    sh "sed -i '' -e 's/\"version\": \".*\"/\"version\": \"${newVersion}\"/' composer.json"
                }
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

