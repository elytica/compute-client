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

          def chore = sh(script: 'git log -n 1 | grep "chore(release):"', returnStatus: true)
          if (chore != 0) {
            sh 'git tag | xargs git tag -d'
            sh 'cp composer.json package.json'
            def standardVersionStatus = sh(script: 'standard-version --tag-prefix "" --no-verify', returnStatus: true)
            if (standardVersionStatus != 0) {
              echo "standard-version failed with exit code ${standardVersionStatus}"
            } else {
              env.NEW_VERSION = sh(script: 'git describe --tags --abbrev=0 HEAD', returnStdout: true).trim()
              sh "git remote set-url origin git@github.com:elytica/compute-client.git"
              sh "git checkout main && git pull"
              sh "sed -i 's/\"version\": \".*\"/\"version\": \"${env.NEW_VERSION}\"/' composer.json"
              sh "git add composer.json composer.lock CHANGELOG.md"
              sh "git commit -m 'chore(release): update composer package version to ${env.NEW_VERSION}'"
              sh "git push --follow-tags"
            }
          }
        }
      }
    }
  }
}

