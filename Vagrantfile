# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  config.vm.network "private_network", type: "dhcp"

  config.vm.define "vm1" do |nginx|
    nginx.vm.box = "ubuntu/trusty64"
  end

end
