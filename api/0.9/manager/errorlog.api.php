<?php

// ---------------------------------------------------------------- //
// 오류로그 목록조회
// Resource: date?query
// date : 선택, 범위지정가능 ('yyyymmdd' or 'yyyymmdd-yyyymmdd')
// query : 선택
//        startno : 시작 일련번호
//        apikey : API KEY
//        requestid : RequestID
// ---------------------------------------------------------------- //
function api_get_errorlog_list($request) {
    global $DB_CONN;

    $resource_info = $request['_metadata']['ResourceInfo'];
    $access_id = $request['_metadata']['ApiAccessId'];

    if ($access_id < 0)
        die2(401, "Unauthorized.");

    $query = 
        "SELECT NO, DATE_FORMAT(DATE, '%Y%m%d') DATE, DATE_FORMAT(TIME, '%H%i%s') TIME, API_KEY, REQUEST_ID, MESSAGE, TRACE\n".
        "  FROM ERRORLOG\n".
        " WHERE 1=1\n";
    $params = array("");

    // resource_info 일자정보 확인
    if (strlen($resource_info) == 8) {
        $query .= "   AND DATE = STR_TO_DATE(?, '%Y%m%d') \n";
        $params[0] .= "s";
        $params[] = $resource_info;
    }
    else if (strlen($resource_info) == 17) {
        @list($data_date1, $data_date2) = explode('-', $resource_info, 2);
        if (!isset($data_date1, $data_date2))
            die2(400, "Bad request. Date format error (yyyymmdd-yyyymmdd)");

        $query .= "   AND DATE BETWEEN STR_TO_DATE(?, '%Y%m%d') AND STR_TO_DATE(?, '%Y%m%d') \n";
        $params[0] .= "ss";
        $params[] = $data_date1;
        $params[] = $data_date2;
    }
    else
        die2(400, "Bad request. Date format error (yyyymmdd or yyyymmdd-yyyymmdd)");

    // startno 추가
    if (isset($request['_request']['startno'])) {
        $query .= "   AND NO > ? \n";
        $params[0] .= "i";
        $params[] = $request['_request']['startno'];
    }

    // apikey 추가
    if (isset($request['_request']['apikey'])) {
        $query .= "   AND API_KEY = (SELECT API_KEY FROM ACCESSKEY WHERE API_KEY = ?) \n";
        $params[0] .= "s";
        $params[] = $request['_request']['apikey'];
    }

    // requestid 추가
    if (isset($request['_request']['requestid'])) {
        $query .= "   AND REQUEST_ID = ? \n";
        $params[0] .= "s";
        $params[] = $request['_request']['requestid'];
    }

    $query .= 
        " ORDER BY NO\n".
        " LIMIT 100 \n";

    $result = array('dataList' => array());

    if ($stmt = @$DB_CONN->prepare($query)) {
        call_user_func_array(array($stmt, "bind_param"), refValues($params));
        $stmt->execute();

        $stmt->bind_result($r_no, $r_date, $r_time, $r_api_key, $r_request_id, $r_message,
            $r_trace);
        while ($stmt->fetch())
        {
            $message = json_decode($r_message, true);
            switch(json_last_error()) {
            case JSON_ERROR_DEPTH:
                $message = $r_message;
                break;
            case JSON_ERROR_CTRL_CHAR:
                $message = $r_message;
                break;
            case JSON_ERROR_SYNTAX:
                $message = $r_message;
                break;
            case JSON_ERROR_NONE:
                break;
            }

            $trace = json_decode($r_trace, true);
            switch(json_last_error()) {
            case JSON_ERROR_DEPTH:
                $trace = $r_trace;
                break;
            case JSON_ERROR_CTRL_CHAR:
                $trace = $r_trace;
                break;
            case JSON_ERROR_SYNTAX:
                $trace = $r_trace;
                break;
            case JSON_ERROR_NONE:
                break;
            }

            $result['dataList'][] = array(
                'no' => $r_no,
                'date' => $r_date,
                'time' => $r_time,
                'apiKey' => $r_api_key,
                'reuqestId' => $r_request_id,
                'message' => $message,
                'trace' => $trace,
            );
        }
        $stmt->close();

        // 조회할 자료가 없으면 404 Not found 로 응답
        if (count($result['dataList']) == 0)
            die2(404, "Not found");
    }
    else
        die2(500, "Internal Server Error (query:api_get_errorlog_list)", $DB_CONN->error);

    return $result;
}

// ---------------------------------------------------------------- //
// 오류로그 삭제
// Resource: date?query
// date : 선택, 범위지정가능 ('yyyymmdd' or 'yyyymmdd-yyyymmdd')
// ---------------------------------------------------------------- //
function api_delete_errorlog($request) {
    global $DB_CONN;

    $resource_info = $request['_metadata']['ResourceInfo'];
    $access_id = $request['_metadata']['ApiAccessId'];

    if ($access_id < 0)
        die2(401, "Unauthorized.");

    $query = 
        "DELETE FROM ERRORLOG\n".
        " WHERE 1=1\n";
    $params = array("");

    // resource_info 일자정보 확인
    if (strlen($resource_info) == 8) {
        $query .= "   AND DATE = STR_TO_DATE(?, '%Y%m%d') \n";
        $params[0] .= "s";
        $params[] = $resource_info;
    }
    else if (strlen($resource_info) == 17) {
        @list($data_date1, $data_date2) = explode('-', $resource_info, 2);
        if (!isset($data_date1, $data_date2))
            die2(400, "Bad request. Date format error (yyyymmdd-yyyymmdd)");

        $query .= "   AND DATE BETWEEN STR_TO_DATE(?, '%Y%m%d') AND STR_TO_DATE(?, '%Y%m%d') \n";
        $params[0] .= "ss";
        $params[] = $data_date1;
        $params[] = $data_date2;
    }
    else
        die2(400, "Bad request. Date format error (yyyymmdd or yyyymmdd-yyyymmdd)");

    $result = array();

    if ($stmt = @$DB_CONN->prepare($query)) {
        call_user_func_array(array($stmt, "bind_param"), refValues($params));
        if (!$stmt->execute()) {
            $stmt->close();
            die2(500, "Can't delete data.", $DB_CONN->error);
        }
        else {
            $stmt->close();
        }
    }
    else
        die2(500, "Internal Server Error (query:api_delete_errorlog)", $DB_CONN->error);

    return $result;
}
