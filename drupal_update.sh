#!/bin/bash

declare -a SITES

HOME=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
SITE_DIR=${HOME}'/web/sites'
#echo "Home set to: ${SITE_DIR}"
cd "${SITE_DIR}"

# Loop:
for SITE in $(ls -d */ | cut -f1 -d'/'); do
  # Skip default site.
  if [ ! "${SITE}" == "default" ]; then
    SITES+=( ${SITE} )
  fi
done

usage() {
    echo "Usage: $0 [option]

Wrapper script for multisite update operations

General operations (safe to execute on-the-fly):
     -h  show this help text

Application operations:
     -U  update server to latest version
     -S  show sites list
     -L  update local Lando to latest version" 1>&2; exit 1;
}

updateApp(){
  N=1
  echo "Home set to: ${HOME}"
  cd "${HOME}"
  echo "$(timestamp) Git pull started."
  git pull
  echo "$(timestamp) Composer install started."
  composer install

  for I in "${SITES[@]}"; do
    echo "$(timestamp) ${N}) ${I} update database started."
    vendor/drush/drush/drush -l ${I} -y updb
    echo "$(timestamp) ${N}) ${I} configuration sync started."
    vendor/drush/drush/drush -l ${I} -y cim
    echo "$(timestamp) ${N}) ${I} cache rebuild started."
    vendor/drush/drush/drush -l ${I} cr
    echo "$(timestamp) ${N}) ${I} remove color translation."
    vendor/drush/drush/drush -l ${I} rct
    echo "$(timestamp) ${N}) ${I} cache rebuild started."
    vendor/drush/drush/drush -l ${I} cr
    ((N=N+1))
  done
}

updateLandoApp(){
  N=1
  cd "${HOME}"
  echo "Home set to: ${HOME}"
  echo "$(timestamp) Composer install started."
  lando composer install
  echo "$(timestamp) ${N}) Default update database started."
  lando drush -y updb
    #echo "$(timestamp) ${N}) Default delete system.schema leftovers."
    #lando drush php-eval "\Drupal::keyValue('system.schema')->delete('standard');"
    #lando drush php-eval "\Drupal::keyValue('system.schema')->delete('install_profile_generator');"
    #lando drush php-eval "\Drupal::keyValue('system.schema')->delete('veebiplatvorm');"
  echo "$(timestamp) ${N}) Default configuration sync started."
  lando drush -y cim
  echo "$(timestamp) ${N}) Default cache rebuild started."
  lando drush cr
  N=2
  for I in "${SITES[@]}"; do
    echo "$(timestamp) ${N}) ${I} update database started."
    lando drush -l ${I} -y updb
      #echo "$(timestamp) ${N}) ${I} delete system.schema leftovers."
      #lando drush -l ${I} php-eval "\Drupal::keyValue('system.schema')->delete('standard');"
      #lando drush -l ${I} php-eval "\Drupal::keyValue('system.schema')->delete('install_profile_generator');"
      #lando drush -l ${I} php-eval "\Drupal::keyValue('system.schema')->delete('veebiplatvorm');"
    echo "$(timestamp) ${N}) ${I} configuration sync started."
    lando drush -l ${I} -y cim
    echo "$(timestamp) ${N}) ${I} remove color translation."
    lando drush -l ${I} rct
    echo "$(timestamp) ${N}) ${I} cache rebuild started."
    lando drush -l ${I} cr
    ((N=N+1))
  done
}

showSites(){
  N=1
  echo "${N}) Default"
  N=2
  for I in "${SITES[@]}"; do
    echo "${N}) ${I}"
    ((N=N+1))
  done
}
# Define a timestamp function
timestamp() {
  date +"%d.%m.%Y %H:%M:%S"
}
while getopts ":hLSUW" O; do
    case "${O}" in
        h)
            usage
            ;;
        U)
            updateApp
            ;;
        L)
          updateLandoApp
          ;;
        S)
          showSites
          ;;
        *)
            usage
            ;;
    esac
done
shift $((OPTIND-1))
