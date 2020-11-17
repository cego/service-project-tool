# ${PROJECT_NAME}
This readme describes how to get ${PROJECT_NAME} up and running, including some useful tools 
and pro tips.

[[_TOC_]]

### Prerequisites
- The Docker deamon (`docker` and `docker-compose`) needs to be installed on your system.
- Make sure you have no other services binding to port `80`, `3306` and `6379`, these will
be taken up by the web-service/api, the mariadb and redis.


### Setup
Follow these steps to get the project up and running:
1. Simply check the project out using git to a destination you like
2. Go to your destination folder
3. Install composer packages by using the provided `app` script
    - `./app composer-install`
4. Build the docker images for the project by using the provided `app` script
    - `./app build`
5. After building is complete, the development environment can be "upped" using the `app` script
    - `./app up`
6. Once the services are up and running, run migrate to create the necessary tables
    - `./app migrate`
7. After migrating is done, apply seeds to insert initial data  
    - `./app seed`

    
### Gaining access to the running services
Once services are running, migrations has run and seeds have been applied, the system
is ready for interaction. The web-service/api will be available at `http://localhost`

As the web-service/api uses a rather special kind of authentication that would be provided
by an `SSO-Proxy` in the staging and production environments, we need to fake having a
proxy in front of our development environment. All that is needed is to simply add
the `remote-user` header, with your username as value, to every request. This is easy
to do with api calls, and a browser extension (i.e. the "Modify Header Value" extension) can get you sorted for web-services.

The `app` script helps you gain access to the less exposed services: 
- `./app sql` Gives you access to the MariaDB CLI
- `./app redis` Gives you access to the Redis CLI
- `./app shell` Gives you access to a shell inside of a docker container with various
tools installed to aid you in your debugging ventures.

    
### Tools
A set of easy-to-use development tools has been provided, all are available using the `app` script.
Running the `app` script without any arguments will display the complete list of available
commands to use. Some of the tools you will undoubtedly run into eventually would be:
- `./app cs` Which will check your code for code style issues
- `./app fixcs` Which will fix your code style issues for you
- `./app test` Which will run all your feature and unit tests
- `./app stan` Which will analyse your code for inconsistencies 


### Pro tips
- Adding a hostname to `127.0.0.1` in your `/etc/hosts` file is a nice way
  to pretty up the url you use to access the web-service/api.
- Make an alias for `./app` e.g. `alias app='./app'` so you don't have to write `./` 
