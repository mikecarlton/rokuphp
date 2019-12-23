# NGINX Installation notes
My notes on installing into an existing installation.

My machine is running Ubuntu 18.04 with pihole, nginx and ffmpeg already installed.
I prefer to use Nginx (vs Apache or others) and can't run 2 webservers on port 80 anyways.

My solution is to create a virtual server (different hostname) to run alongside existing pihole service.

#### Create the PHP app

Install the files

```
mkdir /var/www/rokucam
tar xvzf ~/rokuphp/html/html.tar.gz --directory /var/www/rokucam --strip-components 1
sudo chown -R www-data:www-data /var/www/rokucam
sudo chmod -R g+rw /var/www/rokucam
```

Create `/etc/nginx/sites-available/rokucam` with this content:

```
server {
        listen 80;
        listen [::]:80;

        root /var/www/rokucam;
        server_name rokucam;
        # access_log /var/log/nginx/rokucam.access.log;

        index index.php;

        location /hls {
                autoindex on;
                alias /dev/shm;
                expires max;
                try_files $uri $uri/ =404;
        }

        location ~ \.php$ {
                include snippets/fastcgi-php.conf;
                fastcgi_pass unix:/run/php/php7.2-fpm.sock;
                fastcgi_param FQDN true;
        }
}
```

Create the DNS entry by adding to the host entry in `/etc/hosts`.  Note that you need to restart dnsmasq from the Pihole admin page to pick this up:

```
192.168.1.1   alix rokucam
```

Make the server available:

```
sudo ln -s /etc/nginx/sites-available/rokucam /etc/nginx/sites-enabled
```

Restart Nginx:

```
sudo nginx -t   # look for any syntax errors
sudo service nginx reload
```

#### Configure IP Camera View Pro on the Roku
1. Go to Settings in the IP Camera View Pro app
2. Configure *PiIP* and enter `rokucam`
3. Save and close

#### Add Cameras
1. Visit [http://rokucam/](http://rokucam/)
2. On first visit, you will be prompted to create a user
3. Login
4. Add camera(s) via ONVIF (recommended) or RTSP directly


#### Note for Specific Cameras
Some cameras, in particular FOSCAM, don't work with ONVIF settings.  Instead, add the camera and specify the RTSP directly, e.g.

| Camera   | RTSP                                   | Screenshot                                       |
|:-------- |:---------------------------------------|:-------------------------------------------------|
| FDT 7903 |`rtsp://<USER>:<PW>@<HOST>:554/11`      |`http://<HOST>/web/auto.jpg?-usr=<USER>&-pwd=<PW>`|
| R2       |`rtsp://<USER>:<PW>@<HOST>:88/videoMain`| --                                               |

Substitute `<USER>`, `<PW>` and `<HOST>` accordingly.

#### Cleaning Up RokuCam HLS Transcoding Processes

You may want to create `/etc/cron.daily/rokucam` to kill HLS processes once a day.  They are started when a camera is viewed on the Roku and if not cleaned up, they will continue to run forever.

```
#!/bin/sh

# kill all hls streams daily (rokucam brings them up, but never shuts them down)
kill `ps uwax | grep ffmpeg | grep hls | awk '{print $2}'`
```
