#!/bin/bash -e
# SPDX-License-Identifier: GPL-3.0-or-later
# SPDX-FileCopyrightText: Copyright © 2021 Erez Geva <ErezGeva2@gmail.com>
#
# @author Erez Geva <ErezGeva2@@gmail.com>
# @copyright © 2021 Erez Geva
#
# script to create Docker contianer for building and create Arch Linux packages
###############################################################################
cmd()
{
  echo "$@"
  "$@"
}
clean_cont()
{
  local -r n=$1
  docker ps -a -f "ancestor=$n" -q | xargs -r docker rm -f
}
clean_unused_images()
{
  # Remove unnamed images
  docker images -f "dangling=true" -q | xargs -r docker image rm -f
}
make_all_args()
{
  local arg
  for arg in USER SRC
  do local -n n=$arg;args+=" --build-arg $arg=$n";done
  args+=" --build-arg UID=$uid"
}
main()
{
  local -r base_dir=$(dirname $(realpath $0))
  local -r name=pacmanbuild
  local -r USER=builder
  local -r SRC=.
  local -r uid=$(id -u)
  cd $base_dir/..
  while getopts 'n' opt; do
    case $opt in
      n)
        local -r no_cache=--no-cache
        ;;
    esac
  done
  clean_cont $name
  make_all_args
  sed -i "s/^COPY --chown=[^ ]*/COPY --chown=$USER/" $base_dir/Dockerfile
  cmd docker build $no_cache -f $base_dir/Dockerfile $args -t $name .
  clean_unused_images
}
main "$@"
ext()
{
docker run -it -w /home/builder/libptpmgmt\
  -v $(realpath .):/home/builder/archlinux pacmanbuild
}
