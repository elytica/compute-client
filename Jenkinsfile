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
          sh "git remote set-url origin git@github.com:elytica/compute-client.git"
          sh "git checkout main && git pull"
          def chore = sh(script: 'git log -n 1 | grep "chore(release):"', returnStatus: true)
          if (chore != 0) {
            def standardVersionStatus = sh(script: 'standard-version --tag-prefix "" --no-verify', returnStatus: true)
            if (standardVersionStatus != 0) {
              echo "standard-version failed with exit code ${standardVersionStatus}"
            } else {
              sh "git push --follow-tags origin main"
            }
          }
        }
      }
    }
  }
}

