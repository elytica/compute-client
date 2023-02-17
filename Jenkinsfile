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
          def standardVersionStatus = sh(script: 'standard-version --release-as "" --tag-prefix "" --no-verify', returnStatus: true)
          if (standardVersionStatus != 0) {
            echo "standard-version failed with exit code ${standardVersionStatus}"
          } else {
            env.NEW_VERSION = sh(script: 'git describe --tags --abbrev=0 HEAD', returnStdout: true).trim()
            sh "sed -i 's/\"version\": \".*\"/\"version\": \"${env.NEW_VERSION}\"/' composer.json"
          }
        }
      }
    }

    stage('Upload to Packagist') {
      steps {
        withCredentials([usernamePassword(credentialsId: 'packagist-username', usernameVariable: 'PACKAGIST_USERNAME', passwordVariable: 'PACKAGIST_APIKEY')]) {
          sh "composer config --global --auth http-basic.repo.packagist.com ${PACKAGIST_USERNAME} '${PACKAGIST_APIKEY}'"
          dsh "composer update --with-dependencies"
          dsh "composer version ${env.NEW_VERSION}"
          dsh "git add composer.json composer.lock"
          dsh "git commit -m 'Update version to ${env.NEW_VERSION}'"
          dsh "git tag ${env.NEW_VERSION}"
          dsh "git push origin ${env.NEW_VERSION}"
          dsh "git push origin HEAD"
        }
      }
    }
  }
}

