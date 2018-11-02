@servers(['prod' => 'peterq.cn'])

{{--部署--}}
@task('deploy', ['on' => 'prod'])
cd ~/projects/eleme-redpack
git reset --hard HEAD
git pull
cd docker-env
docker-compose restart
@endtask

{{--初始化--}}
@task('init', ['on' => 'prod'])
cd ~/projects
git clone git@gitee.com:peterq/eleme-redpack.git
cd eleme-redpack/docker-env
docker-compose up -d
@endtask
