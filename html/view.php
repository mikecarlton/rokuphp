<?php
error_reporting(E_ALL ^ E_WARNING);     // suppress some noise from zend

require_once("dm.php");

global $dm;
$datadir = $dm->GetDataPath();
$rtsp = "";
$app = "";
$infile_options = "";
$outfile_options = "";
$disable_audio = "";
$outfile_dir = "";
$sound = 0;

$configdir = $dm->GetConfigPath();

if(file_exists($configdir . "streamer.xml"))
{
   $stream = simplexml_load_file($configdir . "streamer.xml");
   $app = (string)$stream->app->name;
   $disable_audio = (string)$stream->audio->disable;
   $infile_options = (string)$stream->hls->infile_options;
   $outfile_options = (string)$stream->hls->outfile_options;
   $outfile_dir = (string)$stream->hls->outfile_dir;
}

if(file_exists($datadir . "cameras.xml"))
{
  // output our prologue
  echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
      <meta charset='utf-8'>
      <meta name='viewport' content='width=device-width, initial-scale=1, shrink-to-fit=no'>
      <title>Camera Dashboard</title>

      <link href='https://vjs.zencdn.net/7.6.6/video-js.css' rel='stylesheet' />
      <script src='https://unpkg.com/video.js/dist/video.js'></script>
      <script src='https://unpkg.com/@videojs/http-streaming/dist/videojs-http-streaming.js'></script>

      <style>
        .main {
          display: flex;
          flex-flow: row wrap;
          justify-content: space-around;
        }
        .box {
          margin: auto;
          text-align: center;
          font-family: sans-serif;
        }
      </style>
    </head>
    <body>
      <div class='main'>\n";

  $cameras = simplexml_load_file($datadir . "cameras.xml");
  foreach($cameras->camera as $cam)
  {
    echo "\n<figure class='box'>\n";

    $rtsp = "\"" . urldecode((string)$cam->url) . "\"";
    $sound = (int)$cam->sound;
    $hls_stream = "";

    // if the process already exists, return the stream address
    // added 'grep hls' to avoid other ffmpegs that might be working with the camera
    $processStr = exec("ps ax | grep -v grep | grep ffmpeg | grep hls | grep " . $rtsp);
    $array = explode(" ", $processStr);
    foreach($array as $line)
    {
       if(substr($line, 0, strlen($outfile_dir)) === $outfile_dir)
       {
          // needed stream is still running, just use it
          $streamfile = substr($line, strlen($outfile_dir));
          $hls_stream = "/hls/" . $streamfile;
       }
    }

    if (empty($hls_stream))
    {
      // if the process doesn't exist (see above comment) start it
      $fprefix = $dm->generateRandomString();
      $fname = $outfile_dir . $fprefix . ".m3u8";
      $ffmpeg = $app . " ";

      if( ! $sound)
      {
        $ffmpeg .= $disable_audio . " ";
      }

      $ffmpeg .= $infile_options . " ";
      $ffmpeg .= $rtsp . " ";
      $ffmpeg .= $outfile_options . " ";
      $ffmpeg .= "\"" . $fname . "\"";
      $ffmpeg .= " &";

      Proc_Close(Proc_Open($ffmpeg, Array(), $foo));

      $starttime = time();
      $timeout = false;

      while(!file_exists($fname) &&  !$timeout)
      {
         if(exec("ps ax | grep -v grep | grep ffmpeg | grep hls | grep " . $rtsp) == "")
         {
            $timeout = true;
         }
         else
         {
            if((time() - $starttime) > 20)
               $timeout = true;
            else
               sleep(1);
         }
      }

      if(!$timeout)
      {
        $hls_stream = "/hls/" . $fprefix . ".m3u8";
      }
    }

    if (empty($hls_stream))
    {
      echo "<h3>Error</h3>\n";
      echo "<p>Command: " . $ffmpeg . "</p>\n";
    }
    else
    {
      // we've got the hls stream for the camera here, output the player box
      echo "  <video-js id='" . $cam->name . "' class='vjs-default-skin' controls preload='auto' width='640'>\n";
      echo "    <source src='" . $hls_stream . "' type='application/x-mpegURL'>\n";
      echo "  </video-js>\n";
      echo "  <script>videojs('" . $cam->name . "').play()</script>\n";
    }

    echo "  <figcaption>" . $cam->name . "\n";
    echo "  <button onclick='this.parentNode.parentNode.style.display=\"none\"'>Hide</button>\n";
    echo "  </figcaption>\n";
    echo "</figure>\n";
  }

  // output our epilog
  echo "
      </div>
    </body>
    </html>\n";
}
?>
