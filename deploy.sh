#!/bin/bash
rsync -avz --exclude='.git' --exclude='node_modules' ./ edmilson@204.216.159.247:/home/edmilson/htdocs/://sinporg.com.br/app
