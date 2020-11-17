#!/usr/bin/env bash

# Sets the project name and namespace for re-usability
PROJECT_NAMESPACE='${--PROJECT_NAMESPACE--}'
PROJECT_NAME='${--PROJECT_NAME--}'

# Setup variable for needed console color definitions
RED='\033[1;31m'
YELLOW='\033[1;33m'
GREEN='\033[1;32m'
NC='\033[0m' # No Color

# Makes sure we use buildkit when building docker images
export DOCKER_BUILDKIT=1

# Displays the help message for the script
function displayHelp() {
    # Be sure to get the filename dynamically
    filename=$(basename "$0")

    # Construct the help screen to be displayed
    printf "${GREEN}App Shell ${YELLOW}version 0.0.1 ${RED}${PROJECT_NAME}${GREEN}\n"
    echo   "----------------------------------------------------------------------------------------------------"
    echo -e "${YELLOW}Usage: ${NC}"
    printf "  %s [option] [arguments]\n" "$filename"
    echo ""
    echo -e "${YELLOW}Options: ${NC}"
    printf "  %-23s %s\n" "up" "Up the development environment"
    printf "  %-23s %s\n" "down" "Down the development environment"
    printf "  %-23s %s\n" "help" "Displays this help screen"
    printf "  %-23s %s\n" "build" "Builds all images"
    printf "  %-23s %s\n" "build [service]" "Builds image for specific service"
    printf "  %-23s %s\n" "migrate" "Run the database migrator"
    printf "  %-23s %s\n" "seed" "Run the database seeder"
    printf "  %-23s %s\n" "shell" "Opens up an application shell"
    printf "  %-23s %s\n" "sql" "Opens up an mariadb shell"
    printf "  %-23s %s\n" "redis" "Opens up an redis shell"
    printf "  %-23s %s\n" "test" "Runs all tests with coverage"
    printf "  %-23s %s\n" "cs" "Runs a CS check WITHOUT fixing code"
    printf "  %-23s %s\n" "fixcs" "Runs a CS check THAT FIXES code"
    printf "  %-23s %s\n" "stan" "Runs phpstan to check code"
    printf "  %-23s %s\n" "composer-install" "Installs composer packages"
}

# Makes sure network exists
function ensureNetworkExists() {
    if [[ ! $(docker network ls -f name=${PROJECT_NAME} -q) ]]; then
        docker network create ${PROJECT_NAME}
    fi
}

# Calls docker run as the current user to prevent file permissions problems
function dockerRunAsUser() {
    mkdir -p "/tmp/.composer/cache/"
    docker run -u "$(id -u):$(id -g)" -v "/tmp/.composer/cache/:/.composer/cache/" $@
}

# Starts up the development docker environment
function upDevelopment() {
    echo -e "${GREEN}Upping development environment"
    echo -e "${GREEN}--------------------------------------- ${NC}"

    # Make sure overlay network exists before starting up any services
    if [[ ! $(docker network ls -f name=${PROJECT_NAME} -q) ]]; then
        docker network create ${PROJECT_NAME}
    fi

    docker-compose -f docker-compose.support.yml -f docker-compose.yml up -d
}

# Shuts down the docker development environment
function downDevelopment() {
    echo -e "${GREEN}Downing development environment"
    echo -e "${GREEN}--------------------------------------- ${NC}"

    docker-compose -f docker-compose.support.yml -f docker-compose.yml down
}

# Builds all/specific images for the project
function build() {
    echo -e "${GREEN}Building targets"
    echo -e "${GREEN}--------------------------------------- ${NC}"

    if [[ $1 ]]; then
        echo -e "${YELLOW}Building [$1] ${NC}"
        docker build -f docker/Dockerfile . --target $1 -t registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/$1

        exit 0
    fi

    echo -e "${YELLOW}Building [${--SERVICE_NAME--}] ${NC}"
    docker build -f docker/Dockerfile . --target ${--SERVICE_NAME--} -t registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/${--SERVICE_NAME--}

    echo -e "${YELLOW}Building [cron] ${NC}"
    docker build -f docker/Dockerfile . --target cron -t registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/cron

    echo -e "${YELLOW}Building [migrator] ${NC}"
    docker build -f docker/Dockerfile . --target migrator -t registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/migrator

    echo -e "${YELLOW}Building [seeder] ${NC}"
    docker build -f docker/Dockerfile . --target seeder -t registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/seeder

    echo -e "${YELLOW}Building [shell] ${NC}"
    docker build -f docker/Dockerfile . --target shell -t registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/shell
}

# Runs the migrator
function migrate() {
    echo -e "${GREEN}Running the migrator"
    echo -e "${GREEN}--------------------------------------- ${NC}"

    dockerRunAsUser --env APP_ENV=local --network ${PROJECT_NAME} -v $(pwd)/project:/project --rm registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/migrator
}

# Runs the seeder
function seed() {
    echo -e "${GREEN}Running the seeder"
    echo -e "${GREEN}--------------------------------------- ${NC}"

    dockerRunAsUser --env APP_ENV=local --network ${PROJECT_NAME} --rm registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/seeder
}

# Opens a debugging shell with the project files loaded and bootstrapped
function shell() {
    echo -e "${GREEN}Running the shell"
    echo -e "${GREEN}--------------------------------------- ${NC}"

    dockerRunAsUser -it --env APP_ENV=local --network ${PROJECT_NAME} -v $(pwd)/project:/project --rm registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/shell
}

# Opens a SQL shell
function openSqlShell() {
    echo -e "${GREEN}Running the sql shell"
    echo -e "${GREEN}--------------------------------------- ${NC}"

    docker-compose -f docker-compose.support.yml exec database mysql -uroot -psecret
}

# Opens a redis shell
function openRedisShell() {
    echo -e "${GREEN}Running the redis shell"
    echo -e "${GREEN}--------------------------------------- ${NC}"

    docker-compose -f docker-compose.support.yml exec redis redis-cli
}

# Runs all PHPUnit tests
function runTests() {
    echo -e "${GREEN}Running tests"
    echo -e "${GREEN}--------------------------------------- ${NC}"

    docker build -f docker/Dockerfile . --target phpunit
}

# Runs the code style checker in dry-run mode
function runCodeStyleCheck() {
    echo -e "${GREEN}Running code style check"
    echo -e "${GREEN}--------------------------------------- ${NC}"

    docker build -f docker/Dockerfile . --target phpcsfixer
}

# Runs CS fixer in a base container
function runCodeStyleFixer() {
    echo -e "${GREEN}Running CS fixer"
    echo -e "${GREEN}--------------------------------------- ${NC}"

    dockerRunAsUser --env APP_ENV=local --network ${PROJECT_NAME} -v $(pwd)/project:/project --rm registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/shell ./vendor/bin/php-cs-fixer fix
}

# Runs the PHPStan code check
function runPhpStanCheck() {
    echo -e "${GREEN}Running PhpStan check"
    echo -e "${GREEN}--------------------------------------- ${NC}"

    docker build -f docker/Dockerfile . --target phpstan
}

# Composer install's vendor folder on local machine
function runComposerInstall() {
    echo -e "${GREEN}Running Composer Install"
    echo -e "${GREEN}--------------------------------------- ${NC}"

    dockerRunAsUser -t --env APP_ENV=local --network ${PROJECT_NAME} -v $(pwd)/project:/project --rm registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/shell composer install
}

# We need to make sure that a network for the project exists
ensureNetworkExists

# Display help if no arguments are passed to the script
if [[ ! $1 ]]; then
    displayHelp
    exit 0
fi

# Case that delegates to the correct function
case "$1" in
    up)
        upDevelopment
        ;;
    down)
        downDevelopment
        ;;
    help)
        displayHelp
        ;;
    build)
        build $2
        ;;
    migrate)
        migrate
        ;;
    seed)
        seed
        ;;
    shell)
        shell
        ;;
    sql)
        openSqlShell
        ;;
    redis)
        openRedisShell
        ;;
    test)
        runTests
        ;;
    cs)
        runCodeStyleCheck
        ;;
    fixcs)
        runCodeStyleFixer
        ;;
    stan)
        runPhpStanCheck
        ;;
    composer-install)
        runComposerInstall
        ;;
    *)
        echo $"Usage: $0 {help}"
        exit 1
esac

exit 0
