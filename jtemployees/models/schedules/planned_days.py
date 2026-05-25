from odoo import models, fields, exceptions
from datetime import datetime, timedelta
import pytz


class jt_emp_planned_days(models.Model):
    _name = 'jtemployees.planned.days'
    _description = 'Schedule planned days'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    date                        = fields.Date(string='Date')
    employee_id                 = fields.Many2one('hr.employee', string='Employee')
    schedule_id                 = fields.Many2one('resource.calendar', string='Working schedule', tracking=True, domain="['|', ('company_id', '=', False), ('company_id', '=', employee_company_id)]")
    employee_company_id         = fields.Many2one('res.company', related='employee_id.company_id', store=True)
    
    
    def write(self, values):
        if 'schedule_id' in values:
            if not values['schedule_id']:
                raise exceptions.ValidationError(self.employee_id.name + " employee's working schedule can not be empty.")
        return super().write(values)
            
    def _message_track(self, fields_iter, initial_values_dict):
        # Call the super method to get the tracking information
        tracked_values = super()._message_track(fields_iter, initial_values_dict)
        
        for record in self:
            
            values = initial_values_dict[record.id]
            
            changes = ""
            if record.schedule_id and values['schedule_id'] != record.schedule_id.id:
                if values['schedule_id']:
                    changes += str(values['schedule_id']['name']) + " → " + str(record.schedule_id.name) + " (" + str(record.date) + ")"
                else:
                    changes += str(record.schedule_id.name) + " (" + str(record.date) + ")"

            if changes != "":
                # Post tracking information
                task_model = self.env['hr.employee'].sudo().browse([record.employee_id.id])
                task_model.message_post(body=changes)

        return tracked_values
    
    def sync_missing_days(self):
        employees = self.env['hr.employee'].sudo().search([])
        for employee in employees:
            if employee.resource_calendar_id:
                repeate_days = self.env['jtemployees.planned.rschedules'].sudo().search([('employee_id', '=', employee.id)])
                repeate_days_pattren_ids = []
                for item in repeate_days:
                    repeate_days_pattren_ids.append(item.schedule_id.id)
                days = self.env['jtemployees.planned.days'].sudo().search([('employee_id', '=', employee.id)])
                
                repeate_days_ids = []
                for item in days:
                    repeate_days_ids.append(item.schedule_id.id)
                    
                last_day = self.env['jtemployees.planned.days'].sudo().search([('employee_id', '=', employee.id)], limit=1, order='id desc')
                if len(days) < 30:
                    if len(days) > 0:
                        last_date = days[len(days) - 1].date
                    else:
                        last_date = datetime.now().date()
                    missing = 30 - len(days)
                    working_schedule = employee.resource_calendar_id.id
                    
                    while missing > 0:
                        last_date = last_date + timedelta(days=1)
                            
                        if last_day:
                            working_schedule = last_day.schedule_id.id
                            
                        # we set the schedule based on the repeate schedules
                        if len(repeate_days) > 0:
                            working_schedule_id = self.fill_with_pattern(1, repeate_days_ids, repeate_days_pattren_ids)
                            if working_schedule_id > 0:
                                working_schedule = self.env['resource.calendar'].sudo().search([('id', '=', working_schedule_id)], limit=1)
                                working_schedule = working_schedule.id
                        
                        last_day = self.env['jtemployees.planned.days'].sudo().create({
                            "date": last_date,
                            "employee_id": employee.id,
                            "schedule_id": working_schedule
                        })
                        missing -= 1
                        
    
    def find_shift(self, result_array, pattern_array):
        m = len(pattern_array)
        n = len(result_array)

        for s in range(m):  # try every possible alignment
            if all(result_array[i] == pattern_array[(s + i) % m] for i in range(n)):
                return s
        return None


    def fill_with_pattern(self, add_n, result_array, pattern_array):
        s = self.find_shift(result_array, pattern_array)
        if s is None:
            return 0

        m = len(pattern_array)
        start = (s + len(result_array)) % m  # next expected index in the pattern

        for i in range(add_n):
            result_array.append(pattern_array[(start + i) % m])

        if len(result_array) == 0:
            return 0
   
        return result_array[len(result_array) - 1]

    
    def reset_for_employee(self, employee_id, requested_working_schedule):
        if not requested_working_schedule:
            return True
        employee = self.env['hr.employee'].sudo().search([('id', '=', employee_id)], limit=1)
        if employee.resource_calendar_id and employee.resource_calendar_id.id != int(requested_working_schedule):
            exists = self.env['jtemployees.planned.days'].sudo().search([('employee_id', '=', employee.id), ('schedule_id', '=', requested_working_schedule)], limit=1)
            if not exists:
                self.create_new_scheduled_days(employee.id,requested_working_schedule)
                return True
        
        hasEmptyDays = self.env['jtemployees.planned.days'].sudo().search([('employee_id', '=', employee_id), ('schedule_id', '=', False)], limit=1)
        
        if hasEmptyDays:
            self.create_new_scheduled_days(employee.id,requested_working_schedule)
        return True
    
    def create_new_scheduled_days(self, employee_id, requested_working_schedule):
        self.env['jtemployees.planned.rschedules'].sudo().search([('employee_id', '=', employee_id)]).unlink()
        self.env['jtemployees.planned.days'].sudo().search([('employee_id', '=', employee_id)]).unlink()
        last_date = datetime.now().date()
        missing = 30
        while missing > 0:
            last_date = last_date + timedelta(days=1)
            self.env['jtemployees.planned.days'].sudo().create({
                "date": last_date,
                "employee_id": employee_id,
                "schedule_id": requested_working_schedule
            })
            missing -= 1
                
    def replicate_previous_day_for_rest(self):
        self.replicate_days(2)
        return {
            'type': 'ir.actions.act_window',
            'res_model': self.employee_id._name,
            'res_id': self.employee_id.id,
            'view_mode': 'form',
            'target': 'current',
        }
        
    def replicate_previous_two_days_for_rest(self):
        self.replicate_days(3)
        return {
            'type': 'ir.actions.act_window',
            'res_model': self.employee_id._name,
            'res_id': self.employee_id.id,
            'view_mode': 'form',
            'target': 'current',
        }
        
    def replicate_previous_three_days_for_rest(self):
        self.replicate_days(4)
        self.ensure_one()
        return {
            'type': 'ir.actions.act_window',
            'res_model': self.employee_id._name,
            'res_id': self.employee_id.id,
            'view_mode': 'form',
            'target': 'current',
        }
        
    def replicate_previous_four_days_for_rest(self):
        self.replicate_days(5)
        self.ensure_one()
        return {
            'type': 'ir.actions.act_window',
            'res_model': self.employee_id._name,
            'res_id': self.employee_id.id,
            'view_mode': 'form',
            'target': 'current',
        }
        
    def replicate_previous_five_days_for_rest(self):
        self.replicate_days(6)
        self.ensure_one()
        return {
            'type': 'ir.actions.act_window',
            'res_model': self.employee_id._name,
            'res_id': self.employee_id.id,
            'view_mode': 'form',
            'target': 'current',
        }
        
    def replicate_previous_six_days_for_rest(self):
        self.replicate_days(7)
        self.ensure_one()
        return {
            'type': 'ir.actions.act_window',
            'res_model': self.employee_id._name,
            'res_id': self.employee_id.id,
            'view_mode': 'form',
            'target': 'current',
        }
        
    def replicate_days(self, day_required):
        current_id = self.id
        days = self.env['jtemployees.planned.days'].sudo().search([('employee_id', '=', self.employee_id.id),
                                                                                 ('id', '>', current_id)])
        days_till_required = self.env['jtemployees.planned.days'].sudo().search([('employee_id', '=', self.employee_id.id),
                                                                                 ('id', '<=', current_id)])
        required_items = days_till_required[day_required * -1:]
        # delete old
        self.env['jtemployees.planned.rschedules'].sudo().search([('employee_id', '=', self.employee_id.id)]).unlink()
        
        for item in required_items:
            self.env['jtemployees.planned.rschedules'].sudo().create({
                "schedule_id": item.schedule_id.id,
                "employee_id": item.employee_id.id
            })
        
        selected_of_required = required_items[0]
        for day in days:
            day.write({
                "schedule_id": selected_of_required.schedule_id.id
            })
            reset_selected = False
            for item in required_items:
                if item.id <= selected_of_required.id:
                    reset_selected = True
                    continue
                selected_of_required = item
                reset_selected = False
                break
            if reset_selected:
                selected_of_required = required_items[0]
                
    def set_schedules(self):
        tz_aware_datetime = datetime.now(pytz.timezone("Asia/Baghdad"))
        current_date = tz_aware_datetime.date()
        days = self.env['jtemployees.planned.days'].sudo().search([('date', '=', current_date)])
        for day in days:
            day.employee_id.update_working_schedule(day.schedule_id.id)
            
        old_days = self.env['jtemployees.planned.days'].sudo().search([('date', '<=', current_date)])
        for day in old_days:
            schedule_id = day.schedule_id
            day_name = day.date.strftime("%A").lower()
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
                
            self.env['jtemployees.passed.days'].sudo().create({
                "employee_id": day.employee_id.id,
                "start_time": schedule_id.jt_start_time,
                "end_time": schedule_id.jt_end_time,
                "date": day.date,
                "is_day_off": is_day_off
            })
        self.env['jtemployees.planned.days'].sudo().search([('date', '<=', current_date)]).unlink()
        self.sync_missing_days()