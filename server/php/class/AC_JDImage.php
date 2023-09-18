<?php

class AC0_JDImage extends JDApiBase
{
	protected function api_compose() {
		$ret = $this->compose($_REQUEST);
		return [
			"path" => $ret
		];
	}

	// uniqName("outdir", "202202", ".jpg") => "202202.jpg" / "202202-1.jpg"
	static function uniqName($dir, $prefix, $postfix) {
		$idx = 0;
		do {
			$f = $prefix . ($idx>0? "_$idx": "") . $postfix;
			$f1 = $dir . '/' . $f;
			++ $idx;
		}
		while (is_file($f1));
		touch($f1);
		return $f;
	}

	static function myQ($s, $forceQuote=false) {
		$s = preg_replace('/([\'"])/', '\\\\$1', $s);
		if ($forceQuote || preg_match('/[^\w_,.#]/', $s))
			return '"' . $s . '"';
		return $s;
	}

	private function composeParam(&$tplContent, $param) {
		foreach ($tplContent as &$tplPage) {
			foreach ($tplPage["list"] as &$e) {
				if (isset($e["name"]) && isset($param[$e["name"]])) {
					$e["value"] = $param[$e["name"]];
				}
			}
		}
	}

	private function createCmd($tplPage, $outFile) {
		// 生成命令行
		$cmd = "magick -gravity northwest \\\n";
		$isEmpty = true;
		foreach ($tplPage["list"] as $e) {
			if (! $e["value"])
				continue;

			$arr = [];
			if ($e["type"] == "image") {
				$arr[] = $e["value"];
				// checkParams($e, ["pos"], "模板错误");
				if (isset($e["pos"])) {
					list ($x, $y) = explode(',', $e["pos"]);
					if (isset($e["size"])) {
						list ($w, $h) = explode(',', $e["size"]);
						$arr[] = "-geometry {$w}x{$h}+$x+$y";
					}
					else {
						$arr[] = "-geometry +$x+$y";
					}
					$arr[] = "-composite";
				}
			}
			else if ($e["type"] == "text") {
				checkParams($e, ["pos", "font", "fill", "size"], "模板错误");
				$drawCmd = [];
				foreach ([
					"font" => "font",
					"fill" => "fill",
					"size" => "font-size",
					"kerning" => "kerning",
					"interline-spacing" => "interline-spacing",
					"stroke" => "stroke",
					"stroke-width" => "stroke-width",
					"decorate" => "decorate",
					"pos" => "text",
				] as $k0 => $k) {
					if (!empty($e[$k0])) {
						$drawCmd[] = $k . ' ' . self::myQ($e[$k0]);
					}
				}
				$s = join(' ', $drawCmd);
				$v = self::myQ($e["value"], true);
				$arr[] = "-draw '$s $v'";
# 				$arr[] = "-font \"" . $e["font"] . "\"";
# 				$arr[] = "-fill \"" . $e["fill"] . "\"";
# 				$arr[] = "-pointsize " . $e["size"];
#				$arr[] = "-draw 'text {$e["pos"]} \"{$e["value"]}\"'";
			}
			else if ($e["type"] == "param") {
				$arr[] = $e["value"];
			}
			if (count($arr) > 0) {
				$cmd .= join(' ', $arr) . " \\\n";
				$isEmpty = false;
			}
		}
		if ($isEmpty)
			jdRet(E_PARAM, "no work", "没有数据，无须合成");

		$cmd .= "$outFile\n";
		return $cmd;
	}

	// 生成命令文件jdimage-cmd.sh供调试. 支持并发.
	// $opt={pre-compose}
	// 返回生成的文件，支持一次多图: [ {path} ]
	function compose($param, $tplContent=null, $opt=[]) {
		$tpl = mparam("template", $param);
		if ($tplContent) {
			if (!isset($tplContent[0]["list"]))
				jdRet(E_PARAM, "bad tpl", "模板格式错误：`$tpl`");
			$tplDir = null; // 即当前BASE_DIR目录
		}
		else { 
			// 根据模板目录，生成$tplContent和$opt
			$tplDir = "upload/jdimage/" . $tpl;
			$tplFile = $tplDir . "/index.json";
			if (! is_file($tplFile))
				jdRet(E_PARAM, "bad tpl file: $tplFile", "找不到模板：`$tpl`");
			$tplContent = @jsonDecode(file_get_contents($tplFile));
			if (!isset($tplContent[0]["list"]))
				jdRet(E_PARAM, "bad tpl file: $tplFile", "模板格式错误：`$tpl`");

			$preFile = $tplDir . "/pre-compose.php";
			if (is_file($preFile)) {
				$opt["pre-compose"] = file_get_contents($preFile);
			}
		}
		$this->composeParam($tplContent, $param);

		if ($opt["pre-compose"]) {
			$env = new ImageComposeScriptEnv($tplContent);
			$env->execScript($opt["pre-compose"], $env);
		}

		$outDir = "out/" . date("Ym");
		$outDir1 = "upload/jdimage/" . $outDir;
		if (!is_dir($outDir1))
			mkdir($outDir1, 0770, true);

		// outFile不含目录名
		$pageCnt = count($tplContent);
		$outFile = self::uniqName($outDir1, date("Ymd_His") . '_' . $tpl . ($pageCnt>1? "-1": ""), ".jpg");

		$cmdArr = [];
		$resArr = [];
		$page = 1;
		foreach ($tplContent as $tplPage) {
			if ($pageCnt > 1 && $page > 1) {
				# "xx-1.jpg" => "xx-2.jpg"
				$outFile = str_replace("-" . ($page-1) . ".jpg", "-" . $page . ".jpg", $outFile);
			}
			if ($tplDir) {
				$outFile1 = "../$outDir/$outFile";
			}
			else {
				$outFile1 = "$outDir1/$outFile";
			}

			$cmd1 = $this->createCmd($tplPage, $outFile1);
			$cmdArr[] = $cmd1;
			$resArr[] = "$outDir1/$outFile";
			++ $page;
		}

		// 如果在模板目录，则在模板目录下执行；否则直接在项目目录下执行。注意文件路径的引用。
		$cmd = "#!/bin/sh\n";
		if ($tplDir) {
			$cmd .= "cd \"$tplDir\"\n";
		}
		$cmd .= join("\n", $cmdArr);
		logit($cmd, true, "debug");
		// hash文件名, 支持并发
		do {
			$f = "jdimage-cmd-" . rand(1000, 9999) . ".sh";
			if (! file_exists($f))
				break;
		} while (true);
		file_put_contents($f, $cmd);
		exec("sh ./$f 2>&1", $out, $rv);
		if ($rv) {
			$outStr = join("\n", $out);
			logit("JDImage.compose fails: $cmd\nrv=$rv, out=$outStr");
			jdRet(E_SERVER, $outStr, "图像合成失败");
		}
		// 不删除，方便调试查看
		rename($f, "jdimage-cmd.sh");
		return $resArr;
	}
}

class AC2_JDImage extends AC0_JDImage
{
}

/*
示例：

	$env->get("地址", "value");
	$env->set("地址", "value", "上海市XX区");
	$env->move("地址", 20, -30); // 横向右移20，纵向上移30
	$n = $env->自动换行("地址", 10); // 一行最多10个字，超过则自动换行，返回实际行数
	$n = $env->行数("地址"); // 取实际行数

*/
class ImageComposeScriptEnv extends ScriptEnv
{
	public $tplContent;

	function __construct(&$tplContent) {
		$this->tplContent = &$tplContent;
	}

	private function parseName(&$name, &$idx) {
		$name = preg_replace_callback('/\[(\d+)\]$/', function ($ms) use (&$idx) {
			$idx = intval($ms[1]);
		}, $name);
	}
	function get($name, $attr, $page=null) {
		$idx = null;
		$this->parseName($name, $idx);
		foreach ($this->tplContent as $page) {
			foreach ($page["list"] as $e) {
				if ($e["name"] == $name) {
					if ($idx !== null && $idx-- != 0)
						continue;
					return $e[$attr];
				}
			}
		}
	}
	function set($name, $attr, $value, $page=null) {
		$idx = null;
		$this->parseName($name, $idx);
		foreach ($this->tplContent as &$page) {
			foreach ($page["list"] as &$e) {
				if ($e["name"] == $name) {
					if ($idx !== null && $idx-- != 0)
						continue;
					if (is_callable($value)) {
						$e[$attr] = $value($e[$attr], $e);
					}
					else {
						$e[$attr] = $value;
					}
				}
			}
		}
	}
	function move($name, $offsetX, $offsetY) {
		return $this->set($name, "pos", function ($value, $e) use ($offsetX, $offsetY) {
			$arr = explode(',', $value);
			$arr[0] += $offsetX;
			$arr[1] += $offsetY;
			return join(',', $arr);
		});
	}

	// 返回总行数，返回行数。不指定$n则直接返回行数
	function 自动换行($name, $n) {
		$v = $this->get($name, "value");
		if (!$v)
			return 0;
		if ($n > 0) {
			$v = preg_replace('/[^\r\n]{'.$n.'}\K(?=.)/u', "\n", $v);
			$this->set($name, "value", $v);
		}
		$cnt = 0;
		preg_replace('/\n/', '\n', $v, -1, $cnt);
		return $cnt+1;
	}
	function 行数($name) {
		return $this->自动换行($name, 0);
	}
}

