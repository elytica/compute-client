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
          sh 'which release-please'
          if (env.EXIT_STATUS != 0) {
            sh 'sudo npm install -g release-please'
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
  }
}

