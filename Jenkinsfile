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
          sh 'which git-conventional-commits'
          if (env.EXIT_STATUS != 0) {
            sh 'sudo npm install -g git-conventional-commits'
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
            env.NEW_VERSION = sh(script: 'git-conventional-commits version', returnStdout: true).trim()
            sh "git-conventional-commits changelog > CHANGELOG.md"
            sh "git remote set-url origin git@github.com:elytica/compute-client.git"
            sh "git checkout main"
            sh "sed -i 's/\"version\": \".*\"/\"version\": \"${env.NEW_VERSION}\"/' composer.json"
            sh "git add composer.json composer.lock CHANGELOG.md"
            sh "git commit -m 'chore(release): Update version to ${env.NEW_VERSION}'"
            sh "git push origin main"
            sh "git push origin ${env.NEW_VERSION}"
        }
      }
    }
  }
}

