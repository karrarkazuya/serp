# -*- coding: utf-8 -*-
from collections import OrderedDict
import base64
from odoo import http, fields
from odoo.exceptions import ValidationError, UserError

from odoo.osv import expression
from odoo.addons.portal.controllers.portal import CustomerPortal, pager as portal_pager
from odoo.http import request
from werkzeug.utils import redirect
from datetime import datetime, timedelta
from pytz import timezone
import json
import math

class hr_test_controller(http.Controller):

    # requests
    @http.route('/jt_api/hr/test/requests/clear/<int:employee_id>', type='http', auth='none', methods=['GET'], csrf=False)
    def requests_history(self, employee_id=0, page=1, **kw):
        try:
            return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            
            if page < 1:
                page = 1
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)

            page_size = 100
            start = (page - 1) * page_size
            end = start + page_size
            
            if employee_id != employee.id and employee_id not in employee.child_ids.ids:
                return self.response_json({'status': 'error', "code": 14, 'message': 'You do not have permission for this employee'})
            
            if employee_id != employee.id:
                employee = request.env['hr.employee'].sudo().browse([employee_id])

            
            data = request.env['jtemployees.requests'].sudo().search([( "employee_id", "=", employee.id), ("deleted", "=", False)])
            
            for item in data:
                item.unlink()
                
            response = {
                'status': 'success'
            }
            # Return user profile
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})

    @http.route('/jt_api/hr/test/requests/confirm', type='http', auth='none', methods=['POST'], csrf=False)
    def confirm_request(self):
        try:
            return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            user = auth_user.user_id
            
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
            params           = request.get_json_data()
            request_id       = params['request_id']
            manager_approved = params['state'] # approved, rejected
            
            if manager_approved not in ['approved', 'rejected']:
                return self.response_json({'status': 'error', "code": 15, 'message': 'Unsupported state'})
            
            request_made = request.env['jtemployees.requests'].sudo().browse([request_id])
            
            if request_made.employee_id.id not in employee.child_ids.ids:
                return self.response_json({'status': 'error', "code": 16,'message': 'You have no permission to change this request'})
            
            if not request_made.manager_approved and request_made.manager_approved == 'rejected':
                return self.response_json({'status': 'error', "code": 17, 'message': 'This request has been rejected, you can not modify a rejected request'})

            payload = {
                    "hr_approved": manager_approved
                }
            
            if request_made.with_user(user).sudo().write(payload):
                return self.response_json(request_made.read(fields=["id", "parent_id", "employee_id", "name", "days_requested", "minutes_requested", "request_type", "manager_approved", "hr_approved", "datetime_from", "datetime_to", "date_from", "date_to", "create_date"]))
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
      
    # evaluations
    @http.route('/jt_api/hr/test/evaluations/clear/<int:employee_id>', type='http', auth='none', methods=['GET'], csrf=False)
    def evaluations_history(self, employee_id=0, page=1, **kw):
        try:
            return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            user = auth_user.user_id
            
            if not page:
                page = 1
            else:
                page = int(page)
                
            if page < 1:
                page = 1
            
            page_size = 100
            start = (page - 1) * page_size
            end = start + page_size
            
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
            if employee_id != employee.id and employee_id not in employee.child_ids.ids:
                return self.response_json({'status': 'error', "code": 14,'message': 'You do not have permission for this employee'})
            
            employee = request.env['hr.employee'].sudo().browse([employee_id])

            points = request.env['jtemployees.points'].sudo().search([( "employee_id", "=", employee.id)])
            
            for item in points:
                item.test_unlink()
            
            response = {
                'status': 'success'
            }
            # Return user profile
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    @http.route('/jt_api/hr/test/location_checkin/clear', type='http', auth='none', methods=['GET'], csrf=False)
    def location_checkin_submit(self, **kw):
        try:
            return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            user = self.get_user(request)
            
            if not user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            employee    = user.employee
            employee_id = user.employee.id
            
            checkins = request.env['jtemployees.location.checkins'].sudo().search([
                (
                    "employee_id", "=", employee_id
                )
            ])
            
            for item in checkins:
                item.unlink()
            
            response = {
                'status': 'success'
            }
            # Return user profile
            return self.response_json(response)
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    
    @http.route('/jt_api/hr/update_allocationsxxc', type='http', auth='none', methods=['GET'], csrf=False)
    def location_checkin_submit(self, **kw):
        return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
        data = [[11,8640,105],[17,11520,240],[22,5280,240],[26,11520,240],[27,7680,120],[28,11520,240],[31,10560,240],[52,5760,30],[69,11520,240],[70,11520,240],[74,0,0],[75,960,0],[77,480,90],[95,0,60],[102,960,120],[103,9120,0],[107,1440,60],[109,11520,240],[112,2880,15],[113,480,0],[115,2880,240],[116,11040,180],[117,11520,240],[118,7680,31],[126,10080,60],[127,2880,60],[33,11520,240],[136,8160,120],[137,4320,120],[138,8640,150],[144,10560,60],[145,11520,240],[146,10560,240],[151,3360,240],[154,11520,240],[172,11520,240],[173,11520,240],[184,9600,145],[187,480,180],[189,11520,240],[191,6240,0],[192,10080,120],[193,1920,180],[194,10080,75],[212,11520,240],[213,11520,240],[214,2400,120],[223,11520,240],[231,10560,0],[244,11520,240],[240,7200,240],[21,11520,240],[47,11520,240],[83,8160,240],[253,9120,0],[255,5280,90],[256,480,0],[257,11520,240],[258,11520,240],[259,11520,240],[260,11520,240],[261,960,60],[262,3840,120],[263,5760,120],[264,11520,240],[265,0,60],[266,11520,240],[267,11520,240],[268,3360,90],[269,11520,240],[270,11520,240],[271,1920,30],[272,11520,240],[274,4320,180],[275,11520,240],[276,11520,240],[277,11520,240],[278,0,120],[279,11520,240],[280,0,0],[281,11520,240],[282,0,0],[284,11520,240],[285,11520,240],[286,11520,240],[288,9120,100],[289,11520,240],[290,5280,180],[291,0,15],[292,11520,240],[293,10560,240],[294,11520,240],[295,11520,240],[296,3360,240],[297,11520,240],[301,480,150],[302,480,60],[303,11520,240],[305,11520,240],[306,1920,0],[307,11520,240],[308,5280,0],[310,11520,240],[312,11520,240],[313,11520,240],[314,0,0],[315,480,0],[316,11520,240],[317,11520,240],[318,0,0],[319,1440,120],[320,480,90],[321,11520,240],[322,960,168],[323,480,120],[324,0,120],[325,6240,110],[326,11520,240],[327,0,50],[328,480,30],[329,3840,240],[330,11520,240],[331,11520,240],[332,960,120],[333,0,0],[336,1920,0],[337,11520,240],[338,11520,240],[339,0,70],[346,11520,240],[347,7200,60],[348,0,0],[349,480,0],[350,11520,240],[351,4800,60],[352,7200,240],[353,3360,90],[357,10560,240],[361,11520,240],[364,11520,240],[365,5760,0],[366,0,30],[367,960,240],[368,11520,240],[369,6720,120],[370,3840,0],[371,4800,180],[372,8640,30],[373,3360,80],[374,9120,60],[375,11520,240],[376,960,60],[377,11520,240],[378,1440,30],[379,0,0],[380,11520,240],[381,11520,240],[382,11520,240],[383,3840,240],[384,11520,240],[385,1920,240],[386,7200,240],[387,11520,240],[388,11520,240],[389,480,240],[391,11520,240],[394,11520,240],[395,7200,240],[396,11520,240],[397,4800,240],[398,11520,240],[399,11520,240],[400,8160,240],[401,11520,240],[402,11520,240],[403,3840,240],[404,1440,110],[405,11520,240],[406,11520,240],[407,9600,240],[408,11520,240],[409,0,30],[410,10560,240],[411,0,0],[412,5280,150],[413,480,0],[414,11520,240],[416,11520,240],[417,960,0],[418,11520,240],[419,4800,120],[420,0,0],[421,5280,180],[422,11520,240],[423,11520,240],[424,11520,240],[425,0,30],[426,7200,240],[427,0,0],[428,480,90],[429,11520,240],[430,11520,240],[431,3840,240],[432,11520,240],[435,4800,0],[436,11520,240],[437,11520,240],[440,0,0],[441,480,105],[443,11520,240],[444,960,95],[445,11520,240],[446,960,0],[447,2880,240],[448,5760,90],[449,1920,30],[450,960,90],[451,11520,240],[452,0,150],[453,11520,240],[454,11520,240],[455,2400,150],[456,10560,240],[457,11520,240],[458,5760,240],[459,11520,240],[460,11520,240],[461,3360,240],[462,1440,240],[463,6240,240],[464,3360,240],[465,11520,240],[466,3840,240],[467,9600,240],[468,11520,240],[469,11520,240],[470,11520,240],[471,11520,240],[472,11520,240],[473,10560,240],[474,960,240],[475,11520,240],[476,11520,240],[477,11520,240],[478,11520,240],[479,11520,140],[480,5280,240],[483,480,60],[487,0,129],[488,2880,0],[489,11520,240],[490,1920,240],[491,11520,240],[492,480,120],[493,8640,240],[494,11520,240],[495,2400,210],[496,960,0],[497,480,0],[498,11520,240],[499,11520,240],[500,11520,240],[501,0,0],[502,960,240],[503,11520,240],[504,11520,240],[505,11520,240],[506,8160,240],[507,3840,240],[508,11520,240],[509,11520,240],[510,960,240],[511,3360,240],[512,11520,240],[513,11520,240],[514,11520,240],[515,480,240],[516,11520,240],[517,11520,240],[518,8160,240],[519,4320,240],[520,0,240],[521,11520,240],[522,3840,240],[523,11520,240],[524,1440,240],[525,11520,240],[526,960,240],[527,7680,240],[528,6240,240],[529,6720,240],[530,10560,240],[534,480,240],[535,0,30],[536,6720,120],[537,1440,60],[538,4800,240],[539,0,0],[540,2400,60],[541,3840,240],[542,11520,240],[543,11520,240],[544,7680,120],[545,0,0],[546,3360,120],[547,11520,240],[548,11520,240],[549,4800,240],[550,11520,240],[551,11520,240],[552,11520,240],[553,11520,240],[554,7680,240],[555,6240,150],[556,480,0],[557,11520,240],[558,11520,240],[559,11520,240],[560,11520,240],[561,11520,240],[562,2400,240],[563,0,180],[564,11520,240],[565,4800,240],[566,1440,45],[567,8160,240],[568,6240,240],[569,5280,0],[570,2880,240],[571,11520,240],[572,1440,240],[573,11520,240],[574,11520,240],[575,1920,240],[576,11520,240],[577,11520,240],[578,960,240],[579,5760,240],[580,10080,240],[581,6240,240],[582,11520,240],[583,11520,240],[584,11520,240],[585,7200,240],[586,11520,240],[587,2880,240],[588,11520,240],[589,11520,240],[590,6240,240],[591,3360,240],[592,11520,240],[593,3360,240],[594,11520,240],[595,7680,240],[596,11520,240],[597,11520,240],[598,7680,240],[599,11520,240],[600,11520,240],[601,480,240],[602,11520,240],[603,11520,240],[604,10560,240],[605,11520,240],[606,8160,240],[607,6240,240],[608,2880,240],[609,11520,240],[610,1920,240],[611,7200,240],[612,10560,240],[613,11520,240],[617,11520,240],[618,960,60],[619,960,30],[620,11520,240],[621,11520,240],[622,11520,240],[623,960,0],[624,6720,240],[625,960,240],[626,11520,240],[631,10080,240],[632,6240,240],[633,11520,240],[634,11520,240],[635,1440,240],[636,6240,240],[637,7200,240],[638,2400,240],[639,8160,240],[640,2880,240],[641,480,240],[642,1440,240],[643,960,240],[644,11520,240],[645,11520,240],[646,11520,240],[647,2400,240],[648,11520,240],[649,11520,240],[650,0,240],[651,4800,240],[652,11520,240],[653,11520,240],[654,9120,240],[655,4800,240],[656,11520,240],[657,1920,240],[658,2880,240],[660,8160,240],[663,7200,240],[665,2400,240],[666,2880,240],[667,960,240],[668,11040,240],[669,11520,240],[670,3360,240],[671,11520,240],[672,11520,240],[673,11520,240],[674,11520,240],[675,3360,240],[676,960,240],[677,2880,240],[678,11520,240],[679,3840,240],[680,11520,240],[681,960,240],[682,11520,240],[683,11520,240],[684,2880,240],[685,11520,240],[686,11520,240],[687,11520,240],[688,11520,240],[689,11520,240],[690,11520,240],[691,1440,240],[692,9120,240],[693,10080,240],[694,11040,240],[699,11520,240],[700,5280,240],[702,0,75],[703,1440,195],[704,11520,240],[705,480,240],[706,11520,240],[707,11520,240],[709,1440,240],[710,4320,240],[711,11520,240],[712,6240,240],[713,11520,240],[714,11520,240],[715,11520,240],[716,11520,240],[717,11520,240],[718,7680,240],[719,3840,240],[720,5280,240],[721,11520,240],[722,8160,240],[725,2400,240],[726,11520,240],[727,9120,240],[729,8160,240],[730,11520,240],[731,4800,240],[732,9600,240],[733,11520,240],[734,0,120],[737,11520,240],[739,0,30],[740,11520,240],[741,4800,240],[742,11520,240],[743,11520,240],[744,11520,240],[745,10080,60],[746,11520,240],[747,2880,0],[748,11520,240],[749,11520,240],[750,7680,240],[751,480,0],[752,11520,240],[753,9600,75],[756,4320,240],[757,10560,5],[760,7200,90],[761,4320,0],[762,11520,240],[763,8640,240],[764,4800,240],[765,11520,240],[766,7680,240],[768,2880,240],[769,0,240],[770,480,240],[771,2400,240],[772,0,240],[773,11520,240],[774,9120,240],[775,3840,240],[776,9600,240],[777,8640,240],[778,11520,240],[779,3360,240],[780,3840,240],[781,8640,240],[782,9120,240],[783,11520,240],[784,1440,240],[785,8640,240],[786,480,240],[787,8640,240],[788,3360,240],[789,5280,240],[790,7200,240],[791,7680,240],[792,11520,240],[793,4320,240],[794,3840,240],[795,1440,240],[796,11520,240],[797,960,240],[798,1920,240],[799,11520,240],[800,5280,240],[801,7680,240],[802,11520,240],[803,7200,50],[804,8160,120],[805,11520,240],[806,0,60],[809,11520,240],[810,11040,240],[811,8640,240],[812,11520,240],[813,11520,240],[814,11040,240],[815,10080,240],[817,7200,80],[818,11520,240],[822,2400,60],[823,6720,240],[824,11520,240],[825,1920,80],[826,4320,0],[827,4320,240],[828,1440,120],[829,7680,240],[830,5280,240],[831,6720,240],[832,5280,170],[833,7200,240],[835,9120,240],[838,3840,240],[839,11520,240],[840,7680,240],[841,11520,240],[842,11520,240],[845,9600,240],[847,10560,240],[848,6240,240],[849,7680,240],[850,4800,240],[851,3360,240],[852,4320,240],[853,0,240],[854,480,240],[855,960,90],[856,4800,240],[858,6240,240],[859,7200,23],[864,7200,240],[865,4800,240],[866,4800,240],[867,1920,240],[868,1920,30],[869,4800,150],[870,6720,240],[871,10080,240],[872,11520,240],[873,7680,240],[874,0,197],[875,3360,240],[876,1440,90],[877,3360,0],[878,7200,240],[879,480,195],[880,0,6],[881,11520,240],[882,2400,120],[883,6720,240],[884,960,240],[885,7200,240],[886,11040,240],[887,1920,240],[888,0,240],[889,11040,240],[890,9120,240],[891,3840,240],[894,9120,240],[895,4800,240],[896,1440,180],[897,2400,120],[898,10560,240],[899,2880,0],[900,4800,240],[901,4800,240],[902,3840,30],[903,1440,0],[904,9120,240],[905,6720,240],[906,1920,120],[907,8160,90],[908,7200,240],[909,5280,240],[910,480,180],[911,1440,240],[912,10560,240],[913,5760,240],[914,7680,240],[915,1920,240],[916,10560,240],[917,2400,240],[918,1920,240],[919,4800,30],[920,4320,240],[922,8160,240],[924,960,0],[925,4320,240],[926,9120,240],[928,6240,240],[929,480,240],[930,4320,240],[931,5760,240],[932,2400,240],[933,8640,240],[935,2400,30],[936,3840,240],[937,480,0],[938,4800,240],[939,2400,60],[940,960,120],[941,7200,240],[942,1920,240],[943,5280,240],[944,8160,240],[945,1440,0],[946,3840,240],[947,1440,60],[948,5760,240],[949,6240,240],[950,6720,240],[951,0,150],[952,0,195],[953,8640,240],[955,3840,240],[956,3840,0],[957,6720,240],[958,960,0],[963,7680,240],[964,1440,0],[965,6720,240],[966,2880,240],[967,7680,240],[968,3840,13],[969,960,120],[970,960,60],[971,1440,30],[979,3840,240],[980,2880,240],[981,7680,240],[982,7680,240],[993,6720,240],[994,5280,240],[995,0,12],[996,6720,240],[997,5760,240],[998,0,93],[999,0,0],[1000,0,90],[1001,0,0],[1002,6720,240],[1003,5280,240],[1004,5760,240],[1005,5280,240],[1006,4800,240],[1007,3840,240],[1008,4800,240],[1009,4320,240],[1010,5280,240],[1011,4800,240],[1012,6240,240],[1013,0,100],[1014,0,0],[1015,2880,49],[1016,960,120],[1017,1920,130],[1018,3840,240],[1019,4320,240],[1020,4800,240],[1021,0,240],[1022,480,240],[1023,960,240],[1024,480,240],[1025,5280,240],[1026,4800,240],[1027,3360,240],[1041,3360,180],[1042,4800,240],[1043,3840,240],[1044,0,150],[1045,1920,240],[1046,4800,240],[1047,960,240],[1048,480,240],[1049,5760,240],[1050,3360,240],[1054,480,240],[1055,2880,240],[1056,4800,240],[1057,4800,240],[1058,2400,240],[1059,1920,240],[1060,3840,240],[1061,1920,240],[1062,2880,9],[1063,2400,130],[1064,960,240],[1065,3360,240],[1066,3840,240],[1067,2880,240],[1068,1440,240],[1069,480,240],[1070,2880,240],[1071,3360,210],[1072,480,60],[1076,2880,240],[1077,960,95],[1083,960,240],[1084,0,240],[1085,1920,240],[1086,1440,240],[1087,1440,240],[1088,480,40],[1089,480,25],[1090,480,60],[1091,1920,240],[1092,1440,240],[1093,960,240],[1094,960,240],[1100,1440,240],[1101,1920,240],[1102,1920,240],[1104,1920,240],[1105,1920,240],[1106,960,240],[1107,960,240]]
        
        for item in data:
            finger_print = item[0]
            leave   = item[1]
            timeoff = item[2]
            
            employee = request.env['hr.employee'].with_user(1).sudo().search([
                (
                    "jt_fingerprint_id", "=", finger_print
                )
            ])
            
            if employee:
                employee.jt_leavedays = leave / 480
                employee.jt_timeoff = timeoff
                
    @http.route('/jt_api/hr/fix_rusul_issue', type='http', auth='none', methods=['GET'], csrf=False)
    def fix_phones_submit(self, **kw):
        return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
        employees     = request.env['hr.employee'].with_user(1).sudo().search([])
        contaact_type = request.env['jtcontacts.ptype'].with_user(1).sudo().search([('slug', '=', 'employee')], limit=1)
        for employee in employees:
            add_employee = True
            for type in employee.user_partner_id.contact_type:
                if type.slug == "employee":
                    add_employee = False
                    break
                
            if add_employee:
                employee.user_partner_id.contact_type = [(4, contaact_type.id)]
            
        return self.response_json({'status': 'error', 'code': 1, 'message': 'done'})
        
    @http.route('/jt_api/hr/fix_attendance', type='http', auth='none', methods=['GET'], csrf=False)
    def fix_attendance_submit(self, **kw):
        
        """
        data = request.env['jtemployees.requests'].with_user(1).sudo().search([])
        for item in data:
            item.compute_general_dates()
        """
            
        data = request.env['hr.attendance'].with_user(1).sudo().search([('jt_worked_hours', '=', 0)])
        for item in data:
            item.calculate_worked_time()
            
        response = {
            'status': 'success'
        }
        return self.response_json(response)
    
    @http.route('/jt_api/hr/fix_passed_days', type='http', auth='none', methods=['GET'], csrf=False)
    def fix_passed_days_submit(self, **kw):
        return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
        
        employees = request.env['hr.employee'].with_user(1).sudo().search([])
        for employee in employees:
            employee_id = employee.id
        
            data = {}
            records = request.env['jtemployees.planned.days'].with_user(1).sudo().search([('employee_id', '=', employee_id)], limit=30)
            if len(records) < 30:
                continue
            first = False
            index = 60
            for item in records:
                if not first:
                    first = item
                data[index] = item.schedule_id.id
                index += 1

            filled = self.fill_missing(data, start=0, end=90)
            new_schedules = []
            index = 0
            while index < 60:
                new_schedules.append(filled[index])
                index += 1
                
            new_schedules.reverse()
            
            request.env['jtemployees.passed.days'].with_user(1).sudo().search([('employee_id', '=', employee_id)]).unlink()
            date = first.date
            index = 1
            for item in new_schedules:
                new_date = date - timedelta(days=index)
                schedule_id = request.env['resource.calendar'].sudo().search([('id', '=', item)], limit=1)
                day_name = new_date.strftime("%A").lower()
                allowed_days = []
                if schedule_id.jt_has_friday:
                    allowed_days.append("friday")
                if schedule_id.jt_has_monday:
                    allowed_days.append("monday")
                if schedule_id.jt_has_saturday:
                    allowed_days.append("saturday")
                if schedule_id.jt_has_sunday:
                    allowed_days.append("sunday")
                if schedule_id.jt_has_thursday:
                    allowed_days.append("thursday")
                if schedule_id.jt_has_tuesday:
                    allowed_days.append("tuesday")
                if schedule_id.jt_has_wednesday:
                    allowed_days.append("wednesday")
                    
                is_day_off = False
                if day_name not in allowed_days:
                    is_day_off = True
                    
                request.env['jtemployees.passed.days'].with_user(1).sudo().create({
                    "employee_id": first.employee_id.id,
                    "date": new_date,
                    "start_time": schedule_id.jt_start_time,
                    "end_time": schedule_id.jt_end_time,
                    "is_day_off": is_day_off
                })
                index += 1

            response = {
                "given": data,
                "new_schedules": new_schedules,
                "result": filled
            }
        return self.response_json(response)
    
    @http.route('/jt_api/hr/fix_missing_fingerprint', type='http', auth='none', methods=['GET'], csrf=False)
    def fix_missing_fingerprint(self, **kw):

        responses = []
        employees = request.env['jtemployees.fd.log'].with_user(1).sudo().search([('check_out', '=', False)])
        for employee in employees:
            fingerprints = request.env['jtemployees.fd.lograw'].with_user(1).sudo().search([('user_id', '=', employee.user_id), ('timestamp', '>', employee.check_in)], order='timestamp asc', limit=5)
            for fingerprint in fingerprints:
                d1 = employee.check_in
                d2 = fingerprint.timestamp
                difference_in_minutes = (d2 - d1).total_seconds() / 60
                if difference_in_minutes < (60 * 10) and difference_in_minutes > (60 * 6):
                    date_used = request.env['jtemployees.fd.log'].with_user(1).sudo().search([('user_id', '=', employee.user_id), ('check_out', '=', fingerprint.timestamp)], limit=1)
                    if not date_used:
                        date_used = request.env['jtemployees.fd.log'].with_user(1).sudo().search([('user_id', '=', employee.user_id), ('check_in', '=', fingerprint.timestamp), ('check_out', '!=', False)], limit=1)
                        if not date_used:
                            responses.append({
                                "id": employee.id,
                                "employee": employee.employee_id.name,
                                "check_in": employee.check_in,
                                "check_out": fingerprint.timestamp,
                                "difference": difference_in_minutes / 60,
                            })
                            break
                    
        return self.response_json(responses)
    
    @http.route('/jt_api/hr/fix_payrolls_employee', type='http', auth='none', methods=['GET'], csrf=False)
    def fix_payrolls_employee(self, **kw):
        return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})

        items = request.env['jtemployees.payrolls.details'].with_user(1).sudo().search([])
        for item in items:
            item._compute_employee()
        items = request.env['jtemployees.payrolls.dsubs'].with_user(1).sudo().search([])
        for item in items:
            item._compute_employee()
            
        response = {
            "given": "success"
        }
        return self.response_json(response)
    
    
    def fill_missing(self, d: dict, start=0, end=None):
        # normalize keys to int (your sample uses string keys)
        d = {int(k): v for k, v in d.items()}
        keys = sorted(d)
        min_i, max_i = keys[0], keys[-1]
        if end is None:
            end = max_i

        # build the known sequence from min_i..max_i (assumes continuous keys in that range)
        seq = [d[i] for i in range(min_i, max_i + 1)]

        # find the smallest period p such that seq repeats with period p
        def is_period(p):
            return all(seq[i] == seq[i % p] for i in range(len(seq)))

        period = None
        for p in range(1, len(seq) + 1):
            if is_period(p):
                period = p
                break

        if period is None:
            raise ValueError("Could not detect a repeating period from the provided data.")

        # backfill/forward-fill using the detected period
        out = {}
        for i in range(start, end + 1):
            out[i] = seq[(i - min_i) % period]
        # ensure original values remain exactly as provided
        out.update(d)
        return out
    
    def response_json(self, responseData):
        response = http.Response(
                json.dumps(responseData, default=str),
                status=200,
                mimetype='application/json'
            )
        response.headers['Access-Control-Allow-Origin'] = '*'
        response.headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS, PUT, DELETE'
        response.headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, Origin, X-Requested-With, Accept'
        return response
    
    
    @http.route('/jt_api/hr/fix_requests_checkins', type='http', auth='none', methods=['GET'], csrf=False)
    def fix_requests_checkins(self, **kw):

        items = request.env['jtemployees.requests'].with_user(1).sudo().search([('fingerprint', '=', False)])
        for item in items:
            item.compute_requests()
     
        response = {
            "given": "success"
        }
        return self.response_json(response)
    
    
    @http.route('/jt_api/hr/fix_locations', type='http', auth='none', methods=['GET'], csrf=False)
    def fix_locations(self, **kw):

        items = request.env['jtemployees.location.areas'].sudo().search([])
        for item in items:
            if item.area_data:
                item.area_data = item.area_data.replace('\n', '').replace('\\', '').replace(' ', '').replace('\'', '"')
     
        response = {
            "given": "success"
        }
        return self.response_json(response)
    
    
    def get_user(self, request):
        if 'Authorization' not in request.httprequest.headers:
            return False
                
        token = request.httprequest.headers['Authorization']
        return request.env['jtapi.users'].sudo().auth_user(token, ['hr'])
