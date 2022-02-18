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

	// 固定生成命令文件: 模板目录/1.sh，输出到out.jpg，不支持并发
	// 返回生成的文件
	function compose($param) {
		$tpl = mparam("template", $param);
		$tplDir = "upload/jdimage/" . $tpl;
		$tplFile = $tplDir . "/index.json";
		if (! is_file($tplFile))
			jdRet(E_PARAM, "bad tpl file: $tplFile", "找不到模板`$tpl`");
		$tplContent = @jsonDecode(file_get_contents($tplFile));
		$outDir = "out/" . date("Ym");
		$outDir1 = "upload/jdimage/" . $outDir;
		if (!is_dir($outDir1))
			mkdir($outDir1, 0770, true);

		// 生成命令行
		$cmd = "magick -gravity northwest \\\n";
		$isEmpty = true;
		foreach ($tplContent["list"] as $e) {
			if (isset($e["name"]) && isset($param[$e["name"]])) {
				$e["value"] = $param[$e["name"]];
			}
			if (! $e["value"])
				continue;

			$arr = [];
			if ($e["type"] == "image") {
				$arr[] = $e["value"];
				// checkParams($e, ["pos"], "模板错误");
				if (isset($e["pos"])) {
					list ($x, $y) = explode(',', $e["pos"]);
					$arr[] = "-geometry +$x+$y";
					$arr[] = "-composite";
				}
			}
			else if ($e["type"] == "text") {
				checkParams($e, ["pos", "font", "fill", "pointsize"], "模板错误");
				$arr[] = "-font \"" . $e["font"] . "\"";
				$arr[] = "-fill \"" . $e["fill"] . "\"";
				$arr[] = "-pointsize " . $e["pointsize"];
				$arr[] = "-draw 'text {$e["pos"]} \"{$e["value"]}\"'";
			}
			if (count($arr) > 0) {
				$cmd .= join(' ', $arr) . " \\\n";
				$isEmpty = false;
			}
		}
		if ($isEmpty)
			jdRet(E_PARAM, "no work", "没有数据，无须合成");

		// outFile不含目录名
		$outFile = self::uniqName($outDir1, date("Ymd_His") . '_' . $tpl, ".jpg");
		$cmd .= "../$outDir/$outFile";
		logit($cmd, true, "debug");

		chdir($tplDir);
		file_put_contents("1.sh", $cmd);
		exec("sh ./1.sh", $out, $rv);
		if ($rv) {
			$outStr = join("\n", $out);
			logit("JDImage.compose fails: $cmd\nrv=$rv, out=$outStr");
			jdRet(E_SERVER, $outStr, "图像合成失败");
		}
		return "$outDir1/$outFile";
	}
}

class AC2_JDImage extends AC0_JDImage
{
}
